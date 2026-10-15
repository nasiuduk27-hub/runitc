<?php

namespace App\Repositories\FilingSystem;

use PDO;
use Throwable;

/**
 * Permintaan akses Filing System (tabel file_access_request, Fase 3).
 *
 * Satu peminta hanya boleh punya satu permintaan pending per target:
 * ditegakkan lewat INSERT ... SELECT ... WHERE NOT EXISTS (bukan unique index
 * parsial). MySQL memakai FROM DUAL; SQLite (uji) tidak mengenalnya, jadi
 * klausa itu dikondisikan per driver supaya perilakunya sama di keduanya.
 */
class FilingAccessRequest
{
    public const TYPE_FILE = 'file';

    public const TYPE_GROUP = 'group';

    private PDO $db;

    public function __construct(PDO $pdoRun)
    {
        $this->db = $pdoRun;
    }

    /**
     * Buat permintaan pending bila belum ada yang pending untuk target sama.
     * Mengembalikan false bila permintaan pending sudah ada (anti-spam).
     */
    public function create(string $targetType, int $targetId, int $requesterId, ?string $message): bool
    {
        $dual = $this->driver() === 'sqlite' ? '' : ' FROM DUAL';

        $sql = "
            INSERT INTO file_access_request
                (target_type, target_id, requester_id, message, status, created_at)
            SELECT ?, ?, ?, ?, 'pending', ?{$dual}
            WHERE NOT EXISTS (
                SELECT 1 FROM file_access_request
                WHERE target_type = ? AND target_id = ? AND requester_id = ? AND status = 'pending'
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $targetType,
            $targetId,
            $requesterId,
            $message,
            date('Y-m-d H:i:s'),
            $targetType,
            $targetId,
            $requesterId,
        ]);

        if ($stmt->rowCount() < 1) {
            return false;
        }

        return true;
    }

    public function hasPending(string $targetType, int $targetId, int $requesterId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM file_access_request
            WHERE target_type = ? AND target_id = ? AND requester_id = ? AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$targetType, $targetId, $requesterId]);

        return (bool) $stmt->fetchColumn();
    }

    private function driver(): string
    {
        try {
            return (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $e) {
            return 'mysql';
        }
    }
}
