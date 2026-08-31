<?php

namespace App\Support\Legacy;

class Notification
{
    private \PDO $pdoRun;

    public function __construct(\PDO $pdoRun)
    {
        $this->pdoRun = $pdoRun;
        $this->ensureTable();
    }

    public function create(
        int $recipientUserId,
        ?int $senderUserId,
        string $type,
        string $title,
        string $message,
        ?string $targetUrl = null,
        ?string $refTable = null,
        ?int $refId = null
    ): void {
        if ($recipientUserId <= 0) {
            return;
        }

        $stmt = $this->pdoRun->prepare("\n            INSERT INTO sys_notifications\n            (recipient_user_id, sender_user_id, type, title, message, target_url, ref_table, ref_id, is_read, created_at)\n            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())\n        ");

        $stmt->execute([
            $recipientUserId,
            $senderUserId,
            $type,
            $title,
            $message,
            $targetUrl,
            $refTable,
            $refId,
        ]);
    }

    public function getUnreadCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $this->pdoRun->prepare("\n            SELECT COUNT(*)\n            FROM sys_notifications\n            WHERE recipient_user_id = ?\n              AND is_read = 0\n        ");
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function getLatest(int $userId, int $limit = 8): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = $this->pdoRun->prepare("\n            SELECT rec_id, type, title, message, target_url, is_read, created_at\n            FROM sys_notifications\n            WHERE recipient_user_id = ?\n            ORDER BY created_at DESC, rec_id DESC\n            LIMIT ?\n        ");
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAll(int $userId, int $limit = 20, int $offset = 0): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = $this->pdoRun->prepare("\n            SELECT rec_id, type, title, message, target_url, is_read, created_at, read_at\n            FROM sys_notifications\n            WHERE recipient_user_id = ?\n            ORDER BY created_at DESC, rec_id DESC\n            LIMIT ? OFFSET ?\n        ");
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTotalCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $this->pdoRun->prepare("\n            SELECT COUNT(*)\n            FROM sys_notifications\n            WHERE recipient_user_id = ?\n        ");
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function markAsRead(int $notificationId, int $userId): ?string
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = $this->pdoRun->prepare("\n            SELECT target_url\n            FROM sys_notifications\n            WHERE rec_id = ?\n              AND recipient_user_id = ?\n            LIMIT 1\n        ");
        $stmt->execute([$notificationId, $userId]);
        $targetUrl = $stmt->fetchColumn();

        if ($targetUrl === false) {
            return null;
        }

        $stmtUpdate = $this->pdoRun->prepare("\n            UPDATE sys_notifications\n            SET is_read = 1, read_at = IFNULL(read_at, NOW())\n            WHERE rec_id = ?\n              AND recipient_user_id = ?\n        ");
        $stmtUpdate->execute([$notificationId, $userId]);

        return (string) $targetUrl;
    }

    public function markAllAsRead(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $stmt = $this->pdoRun->prepare("\n            UPDATE sys_notifications\n            SET is_read = 1, read_at = IFNULL(read_at, NOW())\n            WHERE recipient_user_id = ?\n              AND is_read = 0\n        ");
        $stmt->execute([$userId]);
    }

    private function ensureTable(): void
    {
        $this->pdoRun->exec("\n            CREATE TABLE IF NOT EXISTS sys_notifications (\n                rec_id INT AUTO_INCREMENT PRIMARY KEY,\n                recipient_user_id INT NOT NULL,\n                sender_user_id INT NULL,\n                type VARCHAR(50) NOT NULL,\n                title VARCHAR(150) NOT NULL,\n                message TEXT NOT NULL,\n                target_url VARCHAR(255) NULL,\n                ref_table VARCHAR(80) NULL,\n                ref_id INT NULL,\n                is_read TINYINT(1) NOT NULL DEFAULT 0,\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                read_at DATETIME NULL,\n                INDEX idx_recipient_read (recipient_user_id, is_read),\n                INDEX idx_created_at (created_at)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\n        ");
    }
}
