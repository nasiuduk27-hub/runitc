<?php
// File: modules/cbt_ops/filing_system/services/FilingPermissionService.php

class FilingPermissionService
{
    private PDO $db;

    public function __construct(PDO $pdoRun)
    {
        $this->db = $pdoRun;
    }

    /**
     * Placeholder untuk mengambil atribut user (Role, Company, Department, dll)
     * TODO: Integrasikan dengan table/session existing di project (sysitc_users, dll).
     */
    public function resolveUserAttributes(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT g.rec_id, g.grpaccess, g.grpacc, g.grpdesc
            FROM sysitc_usracc ua
            INNER JOIN sysitc_grpacc g
                ON g.grpaccess = ua.access_code
                AND g.grpacc = ua.access_account
            WHERE ua.user_rec_id = ?
              AND ua.access_code IN ('03', '04')
        ");
        $stmt->execute([$userId]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $roleIds = [];
        $isAdmin = false;
        foreach ($roles as $role) {
            $roleIds[] = (string) $role['rec_id'];
            $grpacc = (string) ($role['grpacc'] ?? '');
            $grpdesc = strtolower((string) ($role['grpdesc'] ?? ''));

            if ($grpacc === '999' || str_contains($grpdesc, 'super admin') || str_contains($grpdesc, 'administrator')) {
                $isAdmin = true;
            }
        }

        $roleIds = $this->expandEquivalentRoleIds($roleIds);

        $companyStmt = $this->db->prepare("
            SELECT cmpcd
            FROM sysitc_users
            WHERE rec_id = ?
            LIMIT 1
        ");
        $companyStmt->execute([$userId]);
        $companyId = $companyStmt->fetchColumn();
        $companyIds = ($companyId !== false && $companyId !== null && $companyId !== '' && (string)$companyId !== '0')
            ? [(string)$companyId]
            : [];

        $groupStmt = $this->db->prepare("
            SELECT group_id
            FROM sys_filegrp_member
            WHERE user_id = ?
        ");
        $groupStmt->execute([$userId]);
        $customGroupIds = array_map('strval', $groupStmt->fetchAll(PDO::FETCH_COLUMN));

        return [
            'is_admin' => $isAdmin,
            'company' => $companyIds,
            'department' => [],       // TODO: Ambil array ID department user
            'division' => [],         // TODO: Ambil array ID division user
            'role' => $roleIds,
            'custom_groups' => $customGroupIds
        ];
    }

    private function expandEquivalentRoleIds(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->db->prepare("\n            SELECT DISTINCT g2.rec_id\n            FROM sysitc_grpacc g1\n            INNER JOIN sysitc_grpacc g2\n                ON g2.grpaccess = g1.grpaccess\n               AND g2.grpacc = g1.grpacc\n            WHERE g1.rec_id IN ({$placeholders})\n        ");
        $stmt->execute($roleIds);

        return array_values(array_unique(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    /**
     * Menghasilkan array ['WHERE clause', [params]] untuk digunakan pada List File.
     */
    public function buildVisibleFilesWhereClause(int $userId, string $tableAlias = 'f'): array
    {
        $attrs = $this->resolveUserAttributes($userId);
        
        $where = "$tableAlias.status = 'active' AND $tableAlias.deleted_at IS NULL";
        $params = [];

        // Admin sees all active files
        if ($attrs['is_admin']) {
            return [$where, $params];
        }

        $conditions = [];
        
        // 1. Owner
        $conditions[] = "$tableAlias.uploaded_by = ?";
        $params[] = $userId;

        // 2. Public Internal
        $conditions[] = "$tableAlias.access_mode = 'public_internal'";

        // 3. Explicit Access Rules
        $accessOrs = ["(fa.access_type = 'user' AND fa.access_value = ?)"];
        $params[] = (string)$userId;

        $typeMapping = [
            'company' => 'company',
            'department' => 'department',
            'division' => 'division',
            'role' => 'role',
            'custom_group' => 'custom_groups'
        ];

        foreach ($typeMapping as $dbType => $attrKey) {
            $vals = $attrs[$attrKey] ?? [];
            if (!empty($vals)) {
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $accessOrs[] = "(fa.access_type = '$dbType' AND fa.access_value IN ($placeholders))";
                foreach ($vals as $v) {
                    $params[] = (string)$v;
                }
            }
        }

        $accessWhere = implode(' OR ', $accessOrs);
        $conditions[] = "EXISTS (
            SELECT 1 FROM sys_filing_access fa 
            WHERE fa.filing_id = $tableAlias.rec_id 
            AND fa.can_view = 1 
            AND ($accessWhere)
        )";

        $where .= " AND (" . implode(' OR ', $conditions) . ")";

        return [$where, $params];
    }

    /**
     * Mendapatkan semua permission flags untuk suatu file terhadap user tertentu.
     */
    public function getPermissionResult(array $file, int $userId): array
    {
        $isOwner = ((int)$file['uploaded_by'] === $userId);
        $statusActive = ($file['status'] === 'active' && empty($file['deleted_at']));
        $attrs = $this->resolveUserAttributes($userId);
        $isAdmin = $attrs['is_admin'];

        $res = [
            'can_view' => false,
            'can_download' => false,
            'can_share' => false,
            'can_manage' => false,
            'reason' => 'none'
        ];

        // File di sampah tetap boleh dikelola owner untuk restore/hapus permanen.
        if (!$statusActive && $file['status'] === 'trashed' && $isOwner) {
            $res['can_view'] = true;
            $res['can_manage'] = true;
            $res['reason'] = 'owner';
            return $res;
        }

        // Jika file tidak aktif dan user bukan admin, tolak semua.
        if (!$statusActive && !$isAdmin) {
            $res['reason'] = 'inactive_status';
            return $res;
        }

        // Rule: Owner atau Admin
        if ($isOwner || $isAdmin) {
            $res['can_view'] = true;
            $res['can_download'] = true;
            $res['can_manage'] = true;
            $res['can_share'] = true;
            $res['reason'] = $isOwner ? 'owner' : 'admin';
        }

        // Rule: Security Level (Confidential)
        $isConfidential = ($file['security_level'] === 'confidential');
        if ($isConfidential) {
            // Confidential tidak boleh di-share secara default meskipun owner.
            $res['can_share'] = false; 
        }

        if (!$isOwner && !$isAdmin) {
            $explicit = $this->getExplicitPermissions((int)$file['rec_id'], $userId, $attrs);

            if ($explicit['has_rule']) {
                $res['can_view'] = $explicit['can_view'];
                $res['can_download'] = $explicit['can_download'];
                $res['can_manage'] = $isConfidential ? false : $explicit['can_manage'];
                $res['can_share'] = $isConfidential ? false : $explicit['can_share'];
                $res['reason'] = 'explicit_rule';

                return $res;
            }

            // Public Internal berlaku hanya kalau tidak ada rule eksplisit yang lebih spesifik.
            if ($file['access_mode'] === 'public_internal') {
                $res['can_view'] = true;
                $res['can_download'] = true;
                $res['reason'] = 'public_internal';
            }
        }

        return $res;
    }

    private function getExplicitPermissions(int $filingId, int $userId, array $attrs): array
    {
        $params = [$filingId, (string)$userId];
        $ors = ["(access_type = 'user' AND access_value = ?)"];

        $typeMapping = [
            'company' => 'company',
            'department' => 'department',
            'division' => 'division',
            'role' => 'role',
            'custom_group' => 'custom_groups'
        ];

        foreach ($typeMapping as $dbType => $attrKey) {
            $vals = $attrs[$attrKey] ?? [];
            if (!empty($vals)) {
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $ors[] = "(access_type = '$dbType' AND access_value IN ($placeholders))";
                foreach ($vals as $v) {
                    $params[] = (string)$v;
                }
            }
        }

        $where = implode(' OR ', $ors);
        $sql = "SELECT 
                    COUNT(*) as rule_count,
                    MAX(can_view) as v, 
                    MAX(can_download) as d, 
                    MAX(can_share) as s, 
                    MAX(can_manage) as m 
                FROM sys_filing_access 
                WHERE filing_id = ? AND ($where)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'has_rule' => $row && (int)($row['rule_count'] ?? 0) > 0,
            'can_view' => (bool)($row['v'] ?? 0),
            'can_download' => (bool)($row['d'] ?? 0),
            'can_share' => (bool)($row['s'] ?? 0),
            'can_manage' => (bool)($row['m'] ?? 0),
        ];
    }

    public function canView(array $file, int $userId): bool
    {
        return $this->getPermissionResult($file, $userId)['can_view'];
    }

    public function canDownload(array $file, int $userId): bool
    {
        return $this->getPermissionResult($file, $userId)['can_download'];
    }

    public function canShare(array $file, int $userId): bool
    {
        return $this->getPermissionResult($file, $userId)['can_share'];
    }

    public function canManage(array $file, int $userId): bool
    {
        return $this->getPermissionResult($file, $userId)['can_manage'];
    }

    public function canViewAudit(array $file, int $userId): bool
    {
        $attrs = $this->resolveUserAttributes($userId);
        if ($attrs['is_admin']) return true;
        
        $res = $this->getPermissionResult($file, $userId);
        return $res['can_manage'] || $res['reason'] === 'owner';
    }

    public function canUpdatePermission(array $file, int $userId): bool
    {
        return $this->canManage($file, $userId);
    }

    public function normalizePermissionRule(array $rule, array $file, int $userId): array
    {
        $canManage = !empty($rule['can_manage']);
        $canShare = !empty($rule['can_share']);
        $canDownload = !empty($rule['can_download']) || $canManage;
        $canView = !empty($rule['can_view']) || $canDownload || $canShare || $canManage;

        // Security level confidential check
        $isAdmin = $this->isAdmin($userId);
        if ($file['security_level'] === 'confidential' && !$isAdmin) {
            $canShare = false;
        }

        return [
            'access_type' => $rule['access_type'] ?? '',
            'access_value' => $rule['access_value'] ?? '',
            'can_view' => $canView ? 1 : 0,
            'can_download' => $canDownload ? 1 : 0,
            'can_share' => $canShare ? 1 : 0,
            'can_manage' => $canManage ? 1 : 0
        ];
    }

    /**
     * Mengecek apakah user memiliki hak admin/superadmin di modul filing.
     * Digunakan untuk operasi berisiko tinggi seperti Permanent Delete.
     */
    public function isAdmin(int $userId): bool
    {
        return $this->resolveUserAttributes($userId)['is_admin'];
    }
}
