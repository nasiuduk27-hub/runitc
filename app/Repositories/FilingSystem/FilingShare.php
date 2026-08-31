<?php

namespace App\Repositories\FilingSystem;

use PDO;
use PDOException;

class FilingShare
{
    private PDO $db;

    public function __construct(PDO $pdoRun)
    {
        $this->db = $pdoRun;
    }

    public function getShareSummary(int $filingId): array
    {
        $stmt = $this->db->prepare('
            SELECT 
                COUNT(*) as total_links,
                SUM(CASE WHEN is_active = 1 AND revoked_at IS NULL THEN 1 ELSE 0 END) as active_links,
                SUM(access_count) as total_access
            FROM sys_filing_share 
            WHERE filing_id = ?
        ');
        $stmt->execute([$filingId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmtDetails = $this->db->prepare('
            SELECT s.rec_id, s.share_code_preview, s.expired_at, s.max_access, s.access_count, s.allow_download, s.is_active, s.revoked_at, s.created_at,
                   u.account_nm as creator_name
            FROM sys_filing_share s
            LEFT JOIN sysitc_users u ON s.created_by = u.rec_id
            WHERE s.filing_id = ?
            ORDER BY s.created_at DESC
        ');
        $stmtDetails->execute([$filingId]);
        $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

        return [
            'stats' => $stats,
            'details' => $details,
        ];
    }

    public function createShare(array $data): array
    {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO sys_filing_share (
                    filing_id, share_code_preview, share_code_hash, created_by,
                    expired_at, max_access, requires_password, password_hash, allow_download
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $data['filing_id'],
                $data['share_code_preview'],
                $data['share_code_hash'],
                $data['created_by'],
                $data['expired_at'] ?: null,
                $data['max_access'] ?: null,
                $data['requires_password'] ? 1 : 0,
                $data['password_hash'] ?: null,
                $data['allow_download'] ? 1 : 0,
            ]);

            return [
                'success' => true,
                'share_id' => $this->db->lastInsertId(),
            ];
        } catch (PDOException $e) {
            error_log('[FilingShare] Create Error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Gagal menyimpan share code ke database.',
            ];
        }
    }

    public function getSharesByFilingId(int $filingId): array
    {
        $stmt = $this->db->prepare('
            SELECT s.*, u.account_nm as creator_name
            FROM sys_filing_share s
            LEFT JOIN sysitc_users u ON s.created_by = u.rec_id
            WHERE s.filing_id = ?
            ORDER BY s.created_at DESC
        ');
        $stmt->execute([$filingId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revokeShare(int $shareId, int $userId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE sys_filing_share 
            SET is_active = 0, revoked_at = NOW() 
            WHERE rec_id = ? AND is_active = 1
        ');

        return $stmt->execute([$shareId]);
    }

    public function findActiveShareByCodeHash(string $hash): ?array
    {
        $stmt = $this->db->prepare('
            SELECT s.*, f.status as file_status, f.deleted_at as file_deleted_at, f.security_level
            FROM sys_filing_share s
            INNER JOIN sys_filing f ON s.filing_id = f.rec_id
            WHERE s.share_code_hash = ? AND s.is_active = 1 AND s.revoked_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$hash]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return $res ?: null;
    }

    public function incrementAccessCount(int $shareId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE sys_filing_share 
            SET access_count = access_count + 1 
            WHERE rec_id = ?
        ');

        return $stmt->execute([$shareId]);
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
