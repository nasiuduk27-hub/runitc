<?php

namespace App\Repositories\FilingSystem;

use PDO;

class FilingSystem
{
    private PDO $db;

    public function __construct(PDO $pdoRun)
    {
        $this->db = $pdoRun;
    }

    public function getFileById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sys_filing WHERE rec_id = ?');
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return $res ?: null;
    }

    public function getFilesByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT * FROM sys_filing WHERE rec_id IN ($placeholders)");
        $stmt->execute(array_values($ids));

        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dict = [];
        foreach ($res as $r) {
            $dict[$r['rec_id']] = $r;
        }

        return $dict;
    }

    public function getActiveFileTypes(): array
    {
        $stmt = $this->db->query('SELECT type_code, type_name FROM sys_filing_filetype WHERE is_active = 1 ORDER BY sort_order ASC');

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function updateMetadata(int $id, array $data, int $userId): bool
    {
        $fields = [];
        $params = [];

        $allowedFields = [
            'display_name', 'declared_file_type', 'security_level',
            'notes', 'keywords', 'expired_at', 'expired_action', 'expired_processed_at',
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        // Auto updated_at handled by MySQL trigger/definition usually, but we can be explicit
        $fields[] = 'updated_at = NOW()';
        $params[] = $id; // For WHERE

        $sql = 'UPDATE sys_filing SET '.implode(', ', $fields).' WHERE rec_id = ?';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function getFileOwnerInfo(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT rec_id, account_nm, alias_nm FROM sysitc_users WHERE rec_id = ?');
        $stmt->execute([$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    public function formatStatusLabel(string $status): string
    {
        $labels = [
            'active' => 'Aktif',
            'expired' => 'Kadaluarsa',
            'archived' => 'Diarsipkan',
            'trashed' => 'Terhapus (Trash)',
            'deleted' => 'Dihapus Permanen',
            'blocked' => 'Diblokir',
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    public function formatSecurityLabel(string $security): string
    {
        $labels = [
            'normal' => 'Normal',
            'restricted' => 'Restricted',
            'confidential' => 'Confidential',
        ];

        return $labels[$security] ?? ucfirst($security);
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
