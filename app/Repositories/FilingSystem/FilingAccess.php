<?php

namespace App\Repositories\FilingSystem;

use PDO;

class FilingAccess
{
    private PDO $db;

    public function __construct(PDO $pdoRun)
    {
        $this->db = $pdoRun;
    }

    public function getRulesByFilingId(int $filingId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM sys_filing_access 
            WHERE filing_id = ?
            ORDER BY created_at ASC
        ');
        $stmt->execute([$filingId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPermissionSummary(int $filingId): array
    {
        return $this->getRulesByFilingId($filingId);
    }

    public function getAccessOptions(): array
    {
        return [
            'user' => $this->getUserOptions(),
            'role' => $this->getRoleOptions(),
            'department' => $this->getDepartmentOptions(),
            'company' => $this->getCompanyOptions(),
            'custom_group' => $this->getCustomGroupOptions(),
        ];
    }

    public function validateAccessValue(string $type, string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return match ($type) {
            'user' => $this->exists('sysitc_users', 'rec_id', $value),
            'role' => $this->exists('sysitc_grpacc', 'rec_id', $value, "grpaccess IN ('03','04')"),
            'department' => $this->exists('sys_msttable', 'code', $value, "tbl_code = '55' AND statrec = 1"),
            'company' => $this->exists('sysitc_users', 'cmpcd', $value, "cmpcd IS NOT NULL AND cmpcd <> '' AND cmpcd <> '0'"),
            'custom_group' => $this->exists('sys_filegrp', 'rec_id', $value, "status = 'active'"),
            default => false,
        };
    }

    private function getUserOptions(): array
    {
        $stmt = $this->db->query("
            SELECT u.rec_id AS value,
                   COALESCE(NULLIF(u.account_nm, ''), l.account_id, CONCAT('User #', u.rec_id)) AS label
            FROM sysitc_users u
            LEFT JOIN sysitc_login l ON l.rec_id = u.login_rec_id
            WHERE u.status = 1
            ORDER BY label ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRoleOptions(): array
    {
        $stmt = $this->db->query("
            SELECT rec_id AS value,
                   CONCAT(COALESCE(NULLIF(grpdesc, ''), grpacc), ' (', grpaccess, '/', grpacc, ')') AS label
            FROM sysitc_grpacc
            WHERE grpaccess IN ('03','04')
            ORDER BY grpaccess ASC, grpacc ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCompanyOptions(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT cmpcd AS value, cmpcd AS label
            FROM sysitc_users
            WHERE cmpcd IS NOT NULL AND cmpcd <> '' AND cmpcd <> '0'
            ORDER BY cmpcd ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getDepartmentOptions(): array
    {
        $stmt = $this->db->query("
            SELECT code AS value,
                   CONCAT(code, ' - ', descr) AS label
            FROM sys_msttable
            WHERE tbl_code = '55' AND statrec = 1
            ORDER BY code ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCustomGroupOptions(): array
    {
        $stmt = $this->db->query("
            SELECT rec_id AS value, group_name AS label
            FROM sys_filegrp
            WHERE status = 'active'
            ORDER BY group_name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function exists(string $table, string $column, string $value, string $extraWhere = '1=1'): bool
    {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ? AND {$extraWhere}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function replaceRules(int $filingId, array $rules, int $userId): bool
    {
        // Delete existing
        $stmtDel = $this->db->prepare('DELETE FROM sys_filing_access WHERE filing_id = ?');
        $stmtDel->execute([$filingId]);

        // Insert new
        if (empty($rules)) {
            return true;
        }

        $sql = 'INSERT INTO sys_filing_access (filing_id, access_type, access_value, can_view, can_download, can_share, can_manage, created_by) VALUES ';
        $placeholders = [];
        $params = [];

        foreach ($rules as $rule) {
            $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($params,
                $filingId,
                $rule['access_type'],
                $rule['access_value'],
                $rule['can_view'] ? 1 : 0,
                $rule['can_download'] ? 1 : 0,
                $rule['can_share'] ? 1 : 0,
                $rule['can_manage'] ? 1 : 0,
                $userId
            );
        }

        $sql .= implode(', ', $placeholders);
        $stmtIns = $this->db->prepare($sql);

        return $stmtIns->execute($params);
    }

    public function logAudit(int $filingId, int $userId, string $action, string $notes): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO sys_filing_audit (
                filing_id, user_id, action, ip_address, user_agent, notes
            ) VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $filingId,
            $userId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $notes,
        ]);
    }
}
