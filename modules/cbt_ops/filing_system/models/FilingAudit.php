<?php
// File: modules/cbt_ops/filing_system/models/FilingAudit.php

require_once __DIR__ . '/../services/FilingPermissionService.php';

class FilingAudit
{
    private PDO $db;

    public function __construct(PDO $pdoRun)
    {
        $this->db = $pdoRun;
    }

    public function getRecentAuditByFile(int $filingId, int $limit = 10): array
    {
        $sql = "
            SELECT a.*, u.account_nm as user_name
            FROM sys_filing_audit a
            LEFT JOIN sysitc_users u ON a.user_id = u.rec_id
            WHERE a.filing_id = ?
            ORDER BY a.created_at DESC
            LIMIT ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $filingId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAuditActions(): array
    {
        return [
            'upload' => 'Upload File',
            'download' => 'Download',
            'download_denied' => 'Download Ditolak',
            'share_download' => 'Download via Share',
            'create_share' => 'Buat Share',
            'share_create_denied' => 'Buat Share Ditolak',
            'revoke_share' => 'Cabut Share',
            'share_access' => 'Akses Share',
            'share_access_denied' => 'Akses Share Ditolak',
            'move_trash' => 'Pindah ke Sampah',
            'trash_denied' => 'Trash Ditolak',
            'restore' => 'Restore',
            'restore_denied' => 'Restore Ditolak',
            'archive' => 'Arsipkan',
            'archive_denied' => 'Arsip Ditolak',
            'restore_archive' => 'Restore Arsip',
            'expiry_archive' => 'Expired ke Arsip',
            'expiry_trash' => 'Expired ke Sampah',
            'expiry_none' => 'Expired',
            'expiry_error' => 'Expired Error',
            'expiry_delete_skipped' => 'Delete Otomatis Dibatalkan',
            'permanent_delete' => 'Hapus Permanen',
            'permanent_delete_denied' => 'Hapus Permanen Ditolak'
        ];
    }

    public function formatActionLabel(string $action): string
    {
        $actions = $this->getAuditActions();
        return $actions[$action] ?? ucwords(str_replace('_', ' ', $action));
    }

    public function countAuditLogs(array $filters = []): int
    {
        list($where, $params) = $this->buildFilterQuery($filters);
        
        $sql = "
            SELECT COUNT(*) 
            FROM sys_filing_audit a
            LEFT JOIN sys_filing f ON a.filing_id = f.rec_id
            WHERE $where
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getAuditLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        list($where, $params) = $this->buildFilterQuery($filters);
        
        $sql = "
            SELECT 
                a.rec_id, a.filing_id, a.user_id, a.action, a.ip_address, a.user_agent, a.notes, a.created_at,
                f.display_name as file_name, f.file_code,
                u.account_nm as user_name
            FROM sys_filing_audit a
            LEFT JOIN sys_filing f ON a.filing_id = f.rec_id
            LEFT JOIN sysitc_users u ON a.user_id = u.rec_id
            WHERE $where
            ORDER BY a.created_at DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
            
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildFilterQuery(array $filters): array
    {
        $where = ["1=1"];
        $params = [];

        // Base Security Filtering (Visibility logic similar to list)
        // This prevents regular users from seeing logs of files they don't own/can't manage
        // Admin overrides are handled by the controller passing appropriate filters.
        if (!empty($filters['allowed_filing_ids']) && is_array($filters['allowed_filing_ids'])) {
             // Not practical if thousands of files. Better is to enforce rule:
             // Regular user can ONLY filter by specific filing_id they have access to, 
             // OR we enforce a subquery.
             // We'll let the controller handle the base security by injecting the explicit $filters['allowed_where_clause']
        }

        if (!empty($filters['base_security_where'])) {
            $where[] = $filters['base_security_where'];
            if (!empty($filters['base_security_params'])) {
                $params = array_merge($params, $filters['base_security_params']);
            }
        }

        if (!empty($filters['filing_id'])) {
            $where[] = "a.filing_id = ?";
            $params[] = (int) $filters['filing_id'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = "a.user_id = ?";
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = "a.action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "a.created_at >= ?";
            $params[] = $filters['date_from'] . " 00:00:00";
        }

        if (!empty($filters['date_to'])) {
            $where[] = "a.created_at <= ?";
            $params[] = $filters['date_to'] . " 23:59:59";
        }

        if (!empty($filters['keyword'])) {
            $where[] = "(a.notes LIKE ? OR f.display_name LIKE ? OR f.file_code LIKE ?)";
            $k = '%' . $filters['keyword'] . '%';
            array_push($params, $k, $k, $k);
        }

        return [implode(' AND ', $where), $params];
    }
}
