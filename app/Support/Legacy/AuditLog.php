<?php

namespace App\Support\Legacy;

class AuditLog
{
    private \PDO $pdoRun;

    public function __construct(\PDO $pdoRun)
    {
        $this->pdoRun = $pdoRun;
        $this->ensureTable();
    }

    public function log(
        int $actorUserId,
        string $action,
        string $targetType,
        ?int $targetId = null,
        array $metadata = []
    ): void {
        $stmt = $this->pdoRun->prepare('
            INSERT INTO sys_audit_log
            (actor_user_id, action, target_type, target_id, metadata_json, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ');

        $stmt->execute([
            $actorUserId,
            $action,
            $targetType,
            $targetId,
            ! empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }

    public static function logAudit(
        \PDO $pdoRun,
        string $action,
        string $targetType,
        ?int $targetId = null,
        array $metadata = [],
        ?int $actorUserId = null
    ): void {
        if ($actorUserId === null) {
            $actorUserId = (int) (auth_user_id());
        }
        $allowSystemActor = str_starts_with($action, 'LOGIN_FAILED');
        if ($actorUserId <= 0 && ! $allowSystemActor) {
            return;
        }

        try {
            $audit = new self($pdoRun);
            $audit->log($actorUserId, $action, $targetType, $targetId, $metadata);
        } catch (\Throwable $e) {
            error_log('AuditLog failed: '.$e->getMessage());
        }
    }

    public function getFiltered(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where = [];
        $params = [];

        if (! empty($filters['action'])) {
            $where[] = 'al.action = ?';
            $params[] = $filters['action'];
        }
        if (! empty($filters['search'])) {
            $where[] = '(al.action LIKE ? OR al.target_type LIKE ? OR u.account_nm LIKE ? OR al.metadata_json LIKE ?)';
            $keyword = '%'.$filters['search'].'%';
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
        }
        if (! empty($filters['date_from'])) {
            $where[] = 'al.created_at >= ?';
            $params[] = $filters['date_from'].' 00:00:00';
        }
        if (! empty($filters['date_to'])) {
            $where[] = 'al.created_at <= ?';
            $params[] = $filters['date_to'].' 23:59:59';
        }
        if (! empty($filters['actor_user_id'])) {
            $where[] = 'al.actor_user_id = ?';
            $params[] = (int) $filters['actor_user_id'];
        }

        $whereClause = $where ? 'WHERE '.implode(' AND ', $where) : '';

        // Count total
        $countStmt = $this->pdoRun->prepare("
            SELECT COUNT(*) FROM sys_audit_log al
            LEFT JOIN sysitc_users u ON u.rec_id = al.actor_user_id
            {$whereClause}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch page
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdoRun->prepare("
            SELECT al.*, u.account_nm
            FROM sys_audit_log al
            LEFT JOIN sysitc_users u ON u.rec_id = al.actor_user_id
            {$whereClause}
            ORDER BY al.created_at DESC
            LIMIT ".(int) $perPage.' OFFSET '.(int) $offset.'
        ');
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public function getDistinctActions(): array
    {
        $stmt = $this->pdoRun->query('SELECT DISTINCT action FROM sys_audit_log ORDER BY action ASC');

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getRecent(int $limit = 10): array
    {
        $stmt = $this->pdoRun->query('
            SELECT al.*, u.account_nm
            FROM sys_audit_log al
            LEFT JOIN sysitc_users u ON u.rec_id = al.actor_user_id
            ORDER BY al.created_at DESC
            LIMIT '.(int) $limit.'
        ');

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function ensureTable(): void
    {
        $this->pdoRun->exec('
            CREATE TABLE IF NOT EXISTS sys_audit_log (
                rec_id INT AUTO_INCREMENT PRIMARY KEY,
                actor_user_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                target_type VARCHAR(80) NULL,
                target_id INT NULL,
                metadata_json TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_actor (actor_user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at),
                INDEX idx_target (target_type, target_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }
}
