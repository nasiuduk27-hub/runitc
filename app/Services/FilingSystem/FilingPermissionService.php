<?php

namespace App\Services\FilingSystem;

use PDO;
use Throwable;

class FilingPermissionService
{
    private PDO $db;

    public function __construct(PDO $pdoRun)
    {
        $this->db = $pdoRun;
    }

    private function usesNewTables(): bool
    {
        return filter_var(getenv('FILING_USE_NEW_TABLES') ?: false, FILTER_VALIDATE_BOOLEAN);
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
        $grpdescs = [];
        $isAdmin = false;
        foreach ($roles as $role) {
            $roleIds[] = (string) $role['rec_id'];
            $grpacc = (string) ($role['grpacc'] ?? '');
            $grpdesc = strtolower((string) ($role['grpdesc'] ?? ''));

            if ($grpacc === '999' || str_contains($grpdesc, 'super admin') || str_contains($grpdesc, 'administrator')) {
                $isAdmin = true;
            }

            if (! empty($role['grpdesc'])) {
                $grpdescs[] = $role['grpdesc'];
            }
        }

        $roleIds = $this->expandEquivalentRoleIds($roleIds);

        $companyStmt = $this->db->prepare('
            SELECT cmpcd
            FROM sysitc_users
            WHERE rec_id = ?
            LIMIT 1
        ');
        $companyStmt->execute([$userId]);
        $companyId = $companyStmt->fetchColumn();
        $companyIds = ($companyId !== false && $companyId !== null && $companyId !== '' && (string) $companyId !== '0')
            ? [(string) $companyId]
            : [];

        $groupStmt = $this->db->prepare('
            SELECT group_id
            FROM sys_filegrp_member
            WHERE user_id = ?
        ');
        $groupStmt->execute([$userId]);
        $customGroupIds = array_map('strval', $groupStmt->fetchAll(PDO::FETCH_COLUMN));

        return [
            'is_admin' => $isAdmin,
            'company' => $companyIds,
            'department' => $this->resolveDepartmentCodes($grpdescs),
            'division' => $this->resolveDepartmentCodes($grpdescs),
            'role' => $roleIds,
            'custom_groups' => $customGroupIds,
        ];
    }

    /**
     * Menentukan kode department/division user berdasarkan grup akses (grpdesc)
     * yang dipetakan ke master division sys_msttable (tbl_code='55').
     *
     * Alias kata kunci dipakai supaya group seperti "Group Logistic Admin"
     * dapat dipetakan ke division LOG (Logistik Division).
     */
    private function resolveDepartmentCodes(array $grpdescs): array
    {
        $codes = [];

        foreach ($grpdescs as $grpdesc) {
            $g = strtolower(trim((string) $grpdesc));

            // 1. Cocokkan kode division yang muncul persis di grpdesc (mis "Group RDSA Admin").
            foreach ($this->loadDivisionMaster() as $code => $descr) {
                if ($code === '' || $descr === '') {
                    continue;
                }

                if (str_contains($g, strtolower($code))) {
                    $codes[$code] = true;
                }
            }

            // 2. Cocokkan frasa panjang (aman dipakai substring match).
            $aliasMap = [
                'human resource' => 'HRD',
                'commercial & industri' => 'CID',
                'test administration' => 'TAD',
                'direct english' => 'DETE',
                'general affairs' => 'GAD',
                'super admin' => 'MAN',
                'it division' => 'ITD',
                'logistik' => 'LOG',
            ];

            foreach ($aliasMap as $keyword => $code) {
                if ($code !== '' && str_contains($g, $keyword)) {
                    $codes[$code] = true;
                }
            }

            // 3. Cocokkan kata kunci pendek (whole word) agar tidak salah cocok substring.
            $wordMap = [
                'hr' => 'HRD',
                'resource' => 'HRD',
                'edd' => 'EDD',
                'education' => 'EDD',
                'cid' => 'CID',
                'tad' => 'TAD',
                'logistic' => 'LOG',
                'warehouse' => 'LOG',
                'rdsa' => 'RDSA',
                'gad' => 'GAD',
                'management' => 'MAN',
                'finance' => 'FAD',
                'product' => 'PMD',
                'communication' => 'MCD',
                'government' => 'GID',
                'de' => 'DETE',
            ];

            foreach ($wordMap as $keyword => $code) {
                if ($code !== '' && preg_match('/\b'.preg_quote($keyword, '/').'\b/', $g)) {
                    $codes[$code] = true;
                }
            }
        }

        return array_keys($codes);
    }

    /**
     * Membaca master division sys_msttable tbl_code='55' => [code => descr].
     */
    private function loadDivisionMaster(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        try {
            $stmt = $this->db->prepare("
                SELECT code, descr FROM sys_msttable
                WHERE tbl_code = '55' AND statrec = 1
                ORDER BY code
            ");
            $stmt->execute();

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cache[(string) $row['code']] = (string) $row['descr'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }

        return $cache;
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
        $params[] = (string) $userId;

        $typeMapping = [
            'company' => 'company',
            'department' => 'department',
            'division' => 'division',
            'role' => 'role',
            'custom_group' => 'custom_groups',
        ];

        foreach ($typeMapping as $dbType => $attrKey) {
            $vals = $attrs[$attrKey] ?? [];
            if (! empty($vals)) {
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $accessOrs[] = "(fa.access_type = '$dbType' AND fa.access_value IN ($placeholders))";
                foreach ($vals as $v) {
                    $params[] = (string) $v;
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

        $where .= ' AND ('.implode(' OR ', $conditions).')';

        return [$where, $params];
    }

    /**
     * Mendapatkan semua permission flags untuk suatu file terhadap user tertentu.
     */
    public function getPermissionResult(array $file, int $userId): array
    {
        $isOwner = ((int) $file['uploaded_by'] === $userId);
        $statusActive = ($file['status'] === 'active' && empty($file['deleted_at']));
        $attrs = $this->resolveUserAttributes($userId);
        $isAdmin = $attrs['is_admin'];

        $res = [
            'can_view' => false,
            'can_download' => false,
            'can_share' => false,
            'can_manage' => false,
            'reason' => 'none',
        ];

        // File di sampah tetap boleh dikelola owner untuk restore/hapus permanen.
        if (! $statusActive && $file['status'] === 'trashed' && $isOwner) {
            $res['can_view'] = true;
            $res['can_manage'] = true;
            $res['reason'] = 'owner';

            return $res;
        }

        // Jika file tidak aktif dan user bukan admin, tolak semua.
        if (! $statusActive && ! $isAdmin) {
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

        if (! $isOwner && ! $isAdmin) {
            $explicit = $this->getExplicitPermissions((int) $file['rec_id'], $userId, $attrs);

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
        if ($this->usesNewTables()) {
            return $this->getExplicitPermissionsNew($filingId, $userId, $attrs);
        }

        $params = [$filingId, (string) $userId];
        $ors = ["(access_type = 'user' AND access_value = ?)"];

        $typeMapping = [
            'company' => 'company',
            'department' => 'department',
            'division' => 'division',
            'role' => 'role',
            'custom_group' => 'custom_groups',
        ];

        foreach ($typeMapping as $dbType => $attrKey) {
            $vals = $attrs[$attrKey] ?? [];
            if (! empty($vals)) {
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $ors[] = "(access_type = '$dbType' AND access_value IN ($placeholders))";
                foreach ($vals as $v) {
                    $params[] = (string) $v;
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
            'has_rule' => $row && (int) ($row['rule_count'] ?? 0) > 0,
            'can_view' => (bool) ($row['v'] ?? 0),
            'can_download' => (bool) ($row['d'] ?? 0),
            'can_share' => (bool) ($row['s'] ?? 0),
            'can_manage' => (bool) ($row['m'] ?? 0),
        ];
    }

    /**
     * Permission eksplisit dari tabel baru file_shareto.
     * share_cat: 0=Private(owner), 1=Personal(user), 2=Department, 3=Company, 4=All User.
     */
    private function getExplicitPermissionsNew(int $filesysId, int $userId, array $attrs): array
    {
        $ors = ['(share_cat = 1 AND othercode = ?)'];
        $params = [(string) $userId];

        $ors[] = '(share_cat = 4)';

        $typeMapping = [
            2 => ['department', 'custom_groups'],
            3 => ['company'],
        ];

        foreach ($typeMapping as $cat => $attrKeys) {
            $vals = [];
            foreach ($attrKeys as $key) {
                foreach (($attrs[$key] ?? []) as $v) {
                    $vals[(string) $v] = true;
                }
            }
            $vals = array_keys($vals);
            if (! empty($vals)) {
                $placeholders = implode(',', array_fill(0, count($vals), '?'));
                $ors[] = "(share_cat = $cat AND othercode IN ($placeholders))";
                foreach ($vals as $v) {
                    $params[] = (string) $v;
                }
            }
        }

        $where = implode(' OR ', $ors);
        $sql = "
            SELECT COUNT(*) AS rule_count
            FROM file_shareto
            WHERE filesys_id = ? AND ($where)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$filesysId], $params));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $hasRule = $row && (int) ($row['rule_count'] ?? 0) > 0;

        // Struktur baru tidak punya flag per-permisi; akses yang terbagi diberi hak view+download.
        return [
            'has_rule' => $hasRule,
            'can_view' => $hasRule,
            'can_download' => $hasRule,
            'can_share' => false,
            'can_manage' => false,
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
        if ($attrs['is_admin']) {
            return true;
        }

        $res = $this->getPermissionResult($file, $userId);

        return $res['can_manage'] || $res['reason'] === 'owner';
    }

    public function canUpdatePermission(array $file, int $userId): bool
    {
        return $this->canManage($file, $userId);
    }

    public function normalizePermissionRule(array $rule, array $file, int $userId): array
    {
        $canManage = ! empty($rule['can_manage']);
        $canShare = ! empty($rule['can_share']);
        $canDownload = ! empty($rule['can_download']) || $canManage;
        $canView = ! empty($rule['can_view']) || $canDownload || $canShare || $canManage;

        // Security level confidential check
        $isAdmin = $this->isAdmin($userId);
        if ($file['security_level'] === 'confidential' && ! $isAdmin) {
            $canShare = false;
        }

        return [
            'access_type' => $rule['access_type'] ?? '',
            'access_value' => $rule['access_value'] ?? '',
            'can_view' => $canView ? 1 : 0,
            'can_download' => $canDownload ? 1 : 0,
            'can_share' => $canShare ? 1 : 0,
            'can_manage' => $canManage ? 1 : 0,
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
