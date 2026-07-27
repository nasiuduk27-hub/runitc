<?php
// File: models/SystemAccess.php

class SystemAccess
{
    private PDO $pdoRun;

    public function __construct(PDO $pdoRun)
    {
        $this->pdoRun = $pdoRun;
    }

    // ==========================================
    // ROLE MANAGEMENT (sysitc_grpacc)
    // ==========================================

    public function getRoles(): array
    {
        $stmt = $this->pdoRun->query("
            SELECT g.*,
                (SELECT COUNT(DISTINCT ua.user_rec_id)
                 FROM sysitc_usracc ua
                 WHERE ua.access_code = g.grpaccess
                   AND ua.access_account = g.grpacc) AS user_count,
                (SELECT COUNT(*)
                 FROM sys_menu_access ma
                 WHERE ma.grpacc_id = g.rec_id) AS menu_count
            FROM sysitc_grpacc g
            ORDER BY g.grpaccess ASC, g.grpacc ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoleById(int $id): ?array
    {
        $stmt = $this->pdoRun->prepare("SELECT * FROM sysitc_grpacc WHERE rec_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function createRole(array $data): int
    {
        $stmt = $this->pdoRun->prepare("
            INSERT INTO sysitc_grpacc (grpaccess, grpacc, grpdesc)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $data['grpaccess'],
            $data['grpacc'],
            $data['grpdesc']
        ]);
        return (int) $this->pdoRun->lastInsertId();
    }

    public function updateRole(int $id, array $data): void
    {
        $stmt = $this->pdoRun->prepare("
            UPDATE sysitc_grpacc SET grpdesc = ? WHERE rec_id = ?
        ");
        $stmt->execute([$data['grpdesc'], $id]);
    }

    public function deleteRole(int $id): bool
    {
        $role = $this->getRoleById($id);
        if (!$role) return false;

        // Proteksi: Superadmin role tidak bisa dihapus
        if ($this->isSuperadminRole($role)) {
            throw new Exception('Role Superadmin tidak dapat dihapus.');
        }

        // Check user dependency
        $userCount = $this->getRoleUserCount($role['grpaccess'], $role['grpacc']);
        if ($userCount > 0) return false;

        // Check menu mapping dependency
        $menuCount = $this->getRoleMenuCount($id);
        if ($menuCount > 0) return false;

        $stmt = $this->pdoRun->prepare("DELETE FROM sysitc_grpacc WHERE rec_id = ?");
        $stmt->execute([$id]);
        return true;
    }

    public function getRoleUserCount(string $grpaccess, string $grpacc): int
    {
        $stmt = $this->pdoRun->prepare("
            SELECT COUNT(DISTINCT user_rec_id)
            FROM sysitc_usracc
            WHERE access_code = ? AND access_account = ?
        ");
        $stmt->execute([$grpaccess, $grpacc]);
        return (int) $stmt->fetchColumn();
    }

    public function getRoleMenuCount(int $grpaccId): int
    {
        $stmt = $this->pdoRun->prepare("
            SELECT COUNT(*) FROM sys_menu_access WHERE grpacc_id = ?
        ");
        $stmt->execute([$grpaccId]);
        return (int) $stmt->fetchColumn();
    }

    // ==========================================
    // MENU TREE & ACCESS
    // ==========================================

    public function getAllMenusFlat(): array
    {
        $stmt = $this->pdoRun->query("
            SELECT rec_id, mst_id, title, url, icon, is_global, is_active
            FROM sys_menus
            WHERE is_active = 1
            ORDER BY mst_id ASC, sort_order ASC, rec_id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMenusTree(): array
    {
        $menus = $this->getAllMenusFlat();
        return $this->buildTree($menus, 0);
    }

    private function buildTree(array $menus, int $parentId): array
    {
        $branch = [];
        foreach ($menus as $menu) {
            if ((int) $menu['mst_id'] === $parentId) {
                $menu['children'] = $this->buildTree($menus, (int) $menu['rec_id']);
                $branch[] = $menu;
            }
        }
        return $branch;
    }

    public function getMenuById(int $id): ?array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT rec_id, mst_id, title, url, icon, is_global, is_active
            FROM sys_menus WHERE rec_id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function addMenu(array $data): int
    {
        $stmt = $this->pdoRun->prepare("
            INSERT INTO sys_menus (mst_id, title, url, icon, is_global, is_active, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int) ($data['mst_id'] ?? 0),
            $data['title'],
            $data['url'] ?? '#',
            $data['icon'] ?? 'fa-solid fa-link',
            (int) ($data['is_global'] ?? 0),
            (int) ($data['is_active'] ?? 1),
            (int) ($data['sort_order'] ?? 0),
        ]);
        return (int) $this->pdoRun->lastInsertId();
    }

    public function updateMenu(int $id, array $data): void
    {
        $stmt = $this->pdoRun->prepare("
            UPDATE sys_menus
            SET mst_id = ?, title = ?, url = ?, icon = ?, is_global = ?, is_active = ?, sort_order = ?
            WHERE rec_id = ?
        ");
        $stmt->execute([
            (int) ($data['mst_id'] ?? 0),
            $data['title'],
            $data['url'] ?? '#',
            $data['icon'] ?? 'fa-solid fa-link',
            (int) ($data['is_global'] ?? 0),
            (int) ($data['is_active'] ?? 1),
            (int) ($data['sort_order'] ?? 0),
            $id,
        ]);
    }

    public function deleteMenu(int $id): void
    {
        // Hapus akses menu terkait
        $stmt = $this->pdoRun->prepare("DELETE FROM sys_menu_access WHERE menu_id = ?");
        $stmt->execute([$id]);

        // Set parent menu anak-anak jadi 0
        $stmt = $this->pdoRun->prepare("UPDATE sys_menus SET mst_id = 0 WHERE mst_id = ?");
        $stmt->execute([$id]);

        // Hapus menu
        $stmt = $this->pdoRun->prepare("DELETE FROM sys_menus WHERE rec_id = ?");
        $stmt->execute([$id]);
    }

    public function getMenuAccessByRole(int $grpaccId): array
    {
        $role = $this->getRoleById($grpaccId);
        if (!$role) {
            return [];
        }

        $roleIds = $this->getRoleIdsByCode((string) $role['grpaccess'], (string) $role['grpacc']);
        if (empty($roleIds)) {
            $roleIds = [$grpaccId];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->pdoRun->prepare("\n            SELECT DISTINCT menu_id\n            FROM sys_menu_access\n            WHERE grpacc_id IN ({$placeholders})\n        ");
        $stmt->execute($roleIds);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function syncRoleMenuAccess(int $grpaccId, array $menuIds): void
    {
        $role = $this->getRoleById($grpaccId);
        if (!$role) {
            throw new Exception('Role tidak ditemukan.');
        }

        // Proteksi: Superadmin harus selalu punya akses ke menu admin
        if ($this->isSuperadminRole($role)) {
            $adminMenuIds = $this->getAdminMenuIds();
            $menuIds = array_unique(array_merge($menuIds, $adminMenuIds));
        }

        $roleIds = $this->getRoleIdsByCode((string) $role['grpaccess'], (string) $role['grpacc']);
        if (empty($roleIds)) {
            $roleIds = [$grpaccId];
        }

        $this->pdoRun->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $this->pdoRun->prepare("DELETE FROM sys_menu_access WHERE grpacc_id IN ({$placeholders})")->execute($roleIds);

            if (!empty($menuIds)) {
                $stmt = $this->pdoRun->prepare("INSERT INTO sys_menu_access (menu_id, grpacc_id) VALUES (?, ?)");
                foreach ($roleIds as $roleId) {
                    foreach ($menuIds as $menuId) {
                        $stmt->execute([(int) $menuId, (int) $roleId]);
                    }
                }
            }
            $this->pdoRun->commit();
        } catch (Exception $e) {
            $this->pdoRun->rollBack();
            throw $e;
        }
    }

    /**
     * Ambil semua menu ID yang berawalan /modules/admin/ — dilindungi untuk Superadmin.
     */
    public function getAdminMenuIds(): array
    {
        $stmt = $this->pdoRun->query("
            SELECT rec_id FROM sys_menus
            WHERE is_active = 1
              AND (url LIKE '/modules/admin/%' OR url LIKE 'modules/admin/%')
        ");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $ids);
    }

    private function getRoleIdsByCode(string $grpaccess, string $grpacc): array
    {
        $stmt = $this->pdoRun->prepare("\n            SELECT rec_id\n            FROM sysitc_grpacc\n            WHERE grpaccess = ? AND grpacc = ?\n        ");
        $stmt->execute([$grpaccess, $grpacc]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Collect all parent menu IDs for given child menu IDs.
     * Ensures parent menus are included so sidebar renders correctly.
     */
    public function ensureParentMenuIds(array $menuIds): array
    {
        if (empty($menuIds)) return [];

        $allMenus = $this->getAllMenusFlat();
        $menuMap = [];
        foreach ($allMenus as $m) {
            $menuMap[(int) $m['rec_id']] = (int) $m['mst_id'];
        }

        $result = array_map('intval', $menuIds);
        foreach ($menuIds as $id) {
            $current = (int) $id;
            while (isset($menuMap[$current]) && $menuMap[$current] > 0) {
                $parent = $menuMap[$current];
                if (!in_array($parent, $result)) {
                    $result[] = $parent;
                }
                $current = $parent;
            }
        }
        return array_unique($result);
    }

    // ==========================================
    // USER ROLE MANAGEMENT
    // ==========================================

    public function updateUserStatus(int $userRecId, int $status): bool
{
    if (!in_array($status, [0, 1], true)) {
        throw new Exception('Status user tidak valid.');
    }

    // Proteksi: jangan nonaktifkan superadmin terakhir
    if ($status === 0 && $this->isUserSuperadmin($userRecId)) {
        $activeCount = $this->countActiveSuperadmins();
        if ($activeCount <= 1) {
            throw new Exception('Tidak dapat menonaktifkan Superadmin terakhir yang masih aktif.');
        }
    }

    $stmt = $this->pdoRun->prepare("
        UPDATE sysitc_users
        SET status = ?
        WHERE rec_id = ?
    ");

    return $stmt->execute([$status, $userRecId]);
}

    public function getUsersWithRoles(array $filters = [], int $limit = 15, int $offset = 0): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where[] = "u.status = 1";
            } elseif ($filters['status'] === 'inactive') {
                $where[] = "u.status != 1";
            }
        }

        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $where[] = "(u.account_nm LIKE ? OR l.account_id LIKE ? OR l.email_id LIKE ?)";
            array_push($params, $s, $s, $s);
        }

        if (!empty($filters['role_id'])) {
            $role = $this->getRoleById((int) $filters['role_id']);
            if ($role) {
                $where[] = "EXISTS (SELECT 1 FROM sysitc_usracc ua2 WHERE ua2.user_rec_id = u.rec_id AND ua2.access_code = ? AND ua2.access_account = ?)";
                $params[] = $role['grpaccess'];
                $params[] = $role['grpacc'];
            }
        }

        if (!empty($filters['unassigned'])) {
            $where[] = "NOT EXISTS (SELECT 1 FROM sysitc_usracc ua3 WHERE ua3.user_rec_id = u.rec_id AND ua3.access_code IN ('01','03','04'))";
        }

        $whereSql = implode(' AND ', $where);

        // Count total
        $countStmt = $this->pdoRun->prepare("
            SELECT COUNT(DISTINCT u.rec_id)
            FROM sysitc_users u
            JOIN sysitc_login l ON u.login_rec_id = l.rec_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Get data
        $stmt = $this->pdoRun->prepare("
            SELECT u.rec_id, u.account_nm, u.status, u.cmpcd,
                   l.account_id, l.email_id, l.lastlogin
            FROM sysitc_users u
            JOIN sysitc_login l ON u.login_rec_id = l.rec_id
            WHERE {$whereSql}
            ORDER BY u.account_nm ASC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach roles
        foreach ($users as &$user) {
            $user['roles'] = $this->getUserRoles((int) $user['rec_id']);
        }
        unset($user);

        return ['users' => $users, 'total' => $total];
    }

    public function getUserList(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where[] = "u.status = 1";
            } elseif ($filters['status'] === 'inactive') {
                $where[] = "u.status != 1";
            }
        }

        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $where[] = "(u.account_nm LIKE ? OR l.account_id LIKE ? OR l.email_id LIKE ?)";
            array_push($params, $s, $s, $s);
        }

        if (!empty($filters['role_id'])) {
            $role = $this->getRoleById((int) $filters['role_id']);
            if ($role) {
                $where[] = "EXISTS (SELECT 1 FROM sysitc_usracc ua2 WHERE ua2.user_rec_id = u.rec_id AND ua2.access_code = ? AND ua2.access_account = ?)";
                $params[] = $role['grpaccess'];
                $params[] = $role['grpacc'];
            }
        }

        if (!empty($filters['unassigned'])) {
            $where[] = "NOT EXISTS (SELECT 1 FROM sysitc_usracc ua3 WHERE ua3.user_rec_id = u.rec_id AND ua3.access_code IN ('01','03','04'))";
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdoRun->prepare("
            SELECT COUNT(DISTINCT u.rec_id)
            FROM sysitc_users u
            JOIN sysitc_login l ON u.login_rec_id = l.rec_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->pdoRun->prepare("
            SELECT u.rec_id, u.account_nm, u.alias_nm, u.status, u.cmpcd, u.dob,
                   l.account_id, l.email_id, l.lastlogin, l.rec_id AS login_rec_id
            FROM sysitc_users u
            JOIN sysitc_login l ON u.login_rec_id = l.rec_id
            WHERE {$whereSql}
            ORDER BY u.account_nm ASC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            $user['roles'] = $this->getUserRoles((int) $user['rec_id']);
        }
        unset($user);

        return ['users' => $users, 'total' => $total];
    }

    public function getUserDetail(int $userRecId): ?array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT u.rec_id, u.account_nm, u.alias_nm, u.status, u.cmpcd, u.dob, u.whatsapp, u.sexmf,
                   l.account_id, l.email_id, l.lastlogin, l.rec_id AS login_rec_id
            FROM sysitc_users u
            JOIN sysitc_login l ON u.login_rec_id = l.rec_id
            WHERE u.rec_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userRecId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return null;

        $user['roles'] = $this->getUserRoles($userRecId);
        return $user;
    }

    public function resetUserPassword(int $userRecId): string
    {
        $stmt = $this->pdoRun->prepare("
            SELECT l.rec_id AS login_rec_id
            FROM sysitc_users u
            JOIN sysitc_login l ON u.login_rec_id = l.rec_id
            WHERE u.rec_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userRecId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('User tidak ditemukan.');
        }

        $tempPassword = bin2hex(random_bytes(4));
        $stmtUpd = $this->pdoRun->prepare("UPDATE sysitc_login SET password_id = MD5(?) WHERE rec_id = ?");
        $stmtUpd->execute([$tempPassword, (int) $row['login_rec_id']]);

        return $tempPassword;
    }

    public function deleteUser(int $userRecId): void
    {
        $detail = $this->getUserDetail($userRecId);
        if (!$detail) {
            throw new Exception('User tidak ditemukan.');
        }

        if ($this->isUserSuperadmin($userRecId)) {
            $activeCount = $this->countActiveSuperadmins();
            if ($activeCount <= 1) {
                throw new Exception('Tidak dapat menghapus Superadmin terakhir yang masih aktif.');
            }
        }

        $this->pdoRun->beginTransaction();
        try {
            $this->pdoRun->prepare("DELETE FROM sysitc_usracc WHERE user_rec_id = ?")->execute([$userRecId]);
            $this->pdoRun->prepare("DELETE FROM sysitc_usermail WHERE user_recid = ?")->execute([$userRecId]);
            $loginRecId = (int) ($detail['login_rec_id'] ?? 0);
            $this->pdoRun->prepare("DELETE FROM sysitc_users WHERE rec_id = ?")->execute([$userRecId]);
            if ($loginRecId > 0) {
                $this->pdoRun->prepare("DELETE FROM sysitc_login WHERE rec_id = ?")->execute([$loginRecId]);
            }
            $this->pdoRun->commit();
        } catch (Exception $e) {
            $this->pdoRun->rollBack();
            throw $e;
        }
    }

    public function getUserRoles(int $userRecId): array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT g.rec_id, g.grpaccess, g.grpacc, g.grpdesc
            FROM sysitc_usracc ua
            JOIN sysitc_grpacc g ON g.grpaccess = ua.access_code AND g.grpacc = ua.access_account
            WHERE ua.user_rec_id = ?
            ORDER BY g.grpaccess ASC
        ");
        $stmt->execute([$userRecId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isSuperadminRole(array $role): bool
    {
        return (
            ($role['grpaccess'] ?? '') === '01'
            && ($role['grpacc'] ?? '') === '999'
            && stripos($role['grpdesc'] ?? '', 'SUPER') !== false
            && stripos($role['grpdesc'] ?? '', 'ADMIN') !== false
        );
    }

    public function isUserSuperadmin(int $userRecId): bool
    {
        $stmt = $this->pdoRun->prepare("
            SELECT COUNT(*)
            FROM sysitc_usracc ua
            JOIN sysitc_grpacc g
                ON g.grpaccess = ua.access_code
               AND g.grpacc = ua.access_account
            WHERE ua.user_rec_id = ?
              AND g.grpaccess = '01'
              AND g.grpacc = '999'
              AND g.grpdesc LIKE '%SUPER%ADMIN%'
        ");
        $stmt->execute([$userRecId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function countActiveSuperadmins(): int
    {
        $stmt = $this->pdoRun->prepare("
            SELECT COUNT(DISTINCT ua.user_rec_id)
            FROM sysitc_usracc ua
            JOIN sysitc_grpacc g
                ON g.grpaccess = ua.access_code
               AND g.grpacc = ua.access_account
            JOIN sysitc_users u
                ON u.rec_id = ua.user_rec_id
            WHERE g.grpaccess = '01'
              AND g.grpacc = '999'
              AND g.grpdesc LIKE '%SUPER%ADMIN%'
              AND u.status = 1
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function assignUserRole(int $userRecId, int $grpaccId): void
    {
        $role = $this->getRoleById($grpaccId);
        if (!$role) {
            throw new Exception('Role tidak ditemukan.');
        }

        // Check exact role pair to avoid overwriting other roles with the same access_code.
        $stmt = $this->pdoRun->prepare("
            SELECT COUNT(*) FROM sysitc_usracc
            WHERE user_rec_id = ? AND access_code = ? AND access_account = ?
        ");
        $stmt->execute([$userRecId, $role['grpaccess'], $role['grpacc']]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if (!$exists) {
            $this->pdoRun->prepare("
                INSERT INTO sysitc_usracc (user_rec_id, access_code, access_account)
                VALUES (?, ?, ?)
            ")->execute([$userRecId, $role['grpaccess'], $role['grpacc']]);
        }
    }

    public function removeUserRole(int $userRecId, string $accessCode, string $accessAccount): void
    {
        // Proteksi: superadmin terakhir tidak bisa kehilangan role-nya sendiri
        if ($accessCode === '01' && $accessAccount === '999') {
            $role = $this->getRoleByCode($accessCode, $accessAccount);
            if ($role && $this->isSuperadminRole($role)) {
                $activeCount = $this->countActiveSuperadmins();
                if ($activeCount <= 1 && $this->isUserSuperadmin($userRecId)) {
                    throw new Exception('Tidak dapat menghapus role Superadmin dari user terakhir yang masih aktif.');
                }
            }
        }

        $this->pdoRun->prepare("
            DELETE FROM sysitc_usracc
            WHERE user_rec_id = ? AND access_code = ? AND access_account = ?
        ")->execute([$userRecId, $accessCode, $accessAccount]);
    }

    private function getRoleByCode(string $grpaccess, string $grpacc): ?array
    {
        $stmt = $this->pdoRun->prepare("
            SELECT * FROM sysitc_grpacc WHERE grpaccess = ? AND grpacc = ? LIMIT 1
        ");
        $stmt->execute([$grpaccess, $grpacc]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // ==========================================
    // PERMISSION OVERVIEW
    // ==========================================

    public function getPermissionOverview(): array
    {
        $stmt = $this->pdoRun->query("
            SELECT m.rec_id AS menu_id, m.mst_id, m.title, m.url, m.is_global,
                   GROUP_CONCAT(DISTINCT g.grpdesc ORDER BY g.grpdesc SEPARATOR ', ') AS role_names
            FROM sys_menus m
            LEFT JOIN sys_menu_access ma ON ma.menu_id = m.rec_id
            LEFT JOIN sysitc_grpacc g ON g.rec_id = ma.grpacc_id
            WHERE m.is_active = 1
            GROUP BY m.rec_id
            ORDER BY m.mst_id ASC, m.rec_id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // DASHBOARD STATS
    // ==========================================

    public function countTotalUsers(): int
    {
        $stmt = $this->pdoRun->query("SELECT COUNT(*) FROM sysitc_users");
        return (int) $stmt->fetchColumn();
    }

    public function countActiveUsers(): int
    {
        $stmt = $this->pdoRun->query("SELECT COUNT(*) FROM sysitc_users WHERE status = 1");
        return (int) $stmt->fetchColumn();
    }

    public function countInactiveUsers(): int
    {
        $stmt = $this->pdoRun->query("SELECT COUNT(*) FROM sysitc_users WHERE status != 1");
        return (int) $stmt->fetchColumn();
    }

    public function countUsersWithoutRole(): int
    {
        $stmt = $this->pdoRun->query("
            SELECT COUNT(DISTINCT u.rec_id)
            FROM sysitc_users u
            WHERE NOT EXISTS (
                SELECT 1 FROM sysitc_usracc ua
                WHERE ua.user_rec_id = u.rec_id
                  AND ua.access_code IN ('01', '03', '04')
            )
        ");
        return (int) $stmt->fetchColumn();
    }

    public function countTotalRoles(): int
    {
        $stmt = $this->pdoRun->query("SELECT COUNT(*) FROM sysitc_grpacc");
        return (int) $stmt->fetchColumn();
    }

    public function countActiveMenus(): int
    {
        $stmt = $this->pdoRun->query("SELECT COUNT(*) FROM sys_menus WHERE is_active = 1");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Ambil aktivitas user terbaru.
     * TODO: butuh tabel audit_log, lihat task 3.6 — saat ini return array kosong.
     */
    public function getRecentUserActivity(int $limit = 10): array
    {
        // Cek apakah tabel sys_audit_log sudah ada
        try {
            $stmt = $this->pdoRun->query("
                SELECT al.*, u.account_nm
                FROM sys_audit_log al
                LEFT JOIN sysitc_users u ON u.rec_id = al.actor_user_id
                ORDER BY al.created_at DESC
                LIMIT " . (int) $limit . "
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Ambil akses gagal terbaru.
     * TODO: butuh tabel audit_log atau tabel login attempt log — saat ini return array kosong.
     */
    public function getRecentFailedAccess(int $limit = 10): array
    {
        try {
            $stmt = $this->pdoRun->query("
                SELECT al.*, u.account_nm
                FROM sys_audit_log al
                LEFT JOIN sysitc_users u ON u.rec_id = al.actor_user_id
                WHERE al.action LIKE '%FAILED%' OR al.action LIKE '%DENIED%' OR al.action LIKE '%BLOCKED%'
                ORDER BY al.created_at DESC
                LIMIT " . (int) $limit . "
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Ringkasan data operasional CBT untuk dashboard.
     * Data didapat dari tabel di database war (t3sTAdm1n, t3sTt4keR5).
     */
    public function getCbtOperationalSummary(): array
    {
        $summary = [
            'total_participants_today' => 0,
            'assigned_participants' => 0,
            'unassigned_participants' => 0,
            'active_rooms' => 0,
            'total_rooms' => 0,
            'completed_rooms' => 0,
        ];

        try {
            $today = date('Y-m-d');

            // Total participants today
            $stmt = $this->pdoRun->query("
                SELECT COUNT(*) FROM t3sTt4keR5 t
                JOIN t3sTAdm1n a ON a.rec_id = t.admin_id
                WHERE DATE(a.testdt) = '" . $this->pdoRun->quote($today) . "'
            ");
            // Fallback: jika tabel tidak ada, biarkan default 0
        } catch (Throwable $e) {
            // Tabel war mungkin tidak terhubung
        }

        return $summary;
    }

    /**
     * Ringkasan filing untuk dashboard.
     */
    public function getFilingSummary(): array
    {
        try {
            $stmt = $this->pdoRun->query("
                SELECT
                    COUNT(*) AS total_uploads,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_uploads
                FROM runit_filing_files
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: ['total_uploads' => 0, 'today_uploads' => 0];
        } catch (Throwable $e) {
            return ['total_uploads' => 0, 'today_uploads' => 0];
        }
    }
}
