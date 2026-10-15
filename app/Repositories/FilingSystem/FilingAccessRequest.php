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
     * Mengembalikan rec_id baris baru, atau null bila permintaan pending
     * sudah ada (anti-spam).
     */
    public function create(string $targetType, int $targetId, int $requesterId, ?string $message): ?int
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
            return null;
        }

        return (int) $this->db->lastInsertId();
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

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM file_access_request WHERE rec_id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Klaim keputusan dengan guard status pending (idempoten/anti balapan):
     * hanya baris yang masih pending yang berubah; false bila sudah diputus.
     */
    public function decide(int $id, string $status, int $deciderId): bool
    {
        if (! in_array($status, ['approved', 'rejected'], true)) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE file_access_request
            SET status = ?, decided_by = ?, decided_at = ?
            WHERE rec_id = ? AND status = 'pending'
        ");
        $stmt->execute([$status, $deciderId, date('Y-m-d H:i:s'), $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Permintaan pending yang berhak diputuskan $deciderUserId:
     * file miliknya + grup yang ia kelola (daftar id grup manager dari pemanggil).
     *
     * @param  int[]  $managedGroupIds
     * @return array<int, array<string, mixed>>
     */
    public function pendingForDecider(int $deciderUserId, array $managedGroupIds): array
    {
        $stmt = $this->db->prepare("
            SELECT r.rec_id, r.target_type, r.target_id, r.requester_id, r.message, r.status, r.created_at,
                   f.file_name AS target_name,
                   COALESCE(NULLIF(u.account_nm, ''), CONCAT('User #', r.requester_id)) AS requester_name
            FROM file_access_request r
            INNER JOIN file_system f ON f.rec_id = r.target_id
            LEFT JOIN sysitc_users u ON u.rec_id = r.requester_id
            WHERE r.status = 'pending' AND r.target_type = 'file' AND f.userid = ?
            ORDER BY r.created_at DESC, r.rec_id DESC
            LIMIT 200
        ");
        $stmt->execute([$deciderUserId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $managedGroupIds = array_values(array_unique(array_filter(array_map('intval', $managedGroupIds), fn (int $id): bool => $id > 0)));
        if (! empty($managedGroupIds)) {
            $placeholders = implode(',', array_fill(0, count($managedGroupIds), '?'));
            $stmt = $this->db->prepare("
                SELECT r.rec_id, r.target_type, r.target_id, r.requester_id, r.message, r.status, r.created_at,
                       g.group_name AS target_name,
                       COALESCE(NULLIF(u.account_nm, ''), CONCAT('User #', r.requester_id)) AS requester_name
                FROM file_access_request r
                INNER JOIN sys_filegrp g ON g.rec_id = r.target_id
                LEFT JOIN sysitc_users u ON u.rec_id = r.requester_id
                WHERE r.status = 'pending' AND r.target_type = 'group' AND g.rec_id IN ($placeholders)
                ORDER BY r.created_at DESC, r.rec_id DESC
                LIMIT 200
            ");
            $stmt->execute($managedGroupIds);
            $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        usort($rows, function (array $a, array $b): int {
            return strcmp((string) $b['created_at'], (string) $a['created_at'])
                ?: ((int) $b['rec_id'] <=> (int) $a['rec_id']);
        });

        return $rows;
    }

    /**
     * Jumlah permintaan pending yang berhak diputuskan $deciderUserId
     * (badge sidebar; tanpa LIMIT supaya angkanya persis).
     *
     * @param  int[]  $managedGroupIds
     */
    public function pendingCountForDecider(int $deciderUserId, array $managedGroupIds): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM file_access_request r
            INNER JOIN file_system f ON f.rec_id = r.target_id
            WHERE r.status = 'pending' AND r.target_type = 'file' AND f.userid = ?
        ");
        $stmt->execute([$deciderUserId]);
        $count = (int) $stmt->fetchColumn();

        $managedGroupIds = array_values(array_unique(array_filter(array_map('intval', $managedGroupIds), fn (int $id): bool => $id > 0)));
        if (! empty($managedGroupIds)) {
            $placeholders = implode(',', array_fill(0, count($managedGroupIds), '?'));
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM file_access_request r
                INNER JOIN sys_filegrp g ON g.rec_id = r.target_id
                WHERE r.status = 'pending' AND r.target_type = 'group' AND g.rec_id IN ($placeholders)
            ");
            $stmt->execute($managedGroupIds);
            $count += (int) $stmt->fetchColumn();
        }

        return $count;
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
