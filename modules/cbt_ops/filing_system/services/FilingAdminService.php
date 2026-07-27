<?php
// File: modules/cbt_ops/filing_system/services/FilingAdminService.php

require_once __DIR__ . '/FilingStorageService.php';
require_once __DIR__ . '/FilingPermissionService.php';

class FilingAdminService
{
    private PDO $db;
    private FilingStorageService $storage;
    private FilingPermissionService $permissionService;

    public function __construct(PDO $pdoRun, FilingStorageService $storage, FilingPermissionService $permissionService)
    {
        $this->db = $pdoRun;
        $this->storage = $storage;
        $this->permissionService = $permissionService;
    }

    public function isFilingSystemAdmin(int $userId): bool
    {
        return $this->permissionService->isAdmin($userId);
    }

    public function requireAdmin(int $userId): void
    {
        if (!$this->isFilingSystemAdmin($userId)) {
            http_response_code(403);
            die("Access Denied: Administrator privileges required.");
        }
    }

    private function logAudit(int $filingId, int $userId, string $action, string $notes): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO sys_filing_audit (
                filing_id, user_id, action, ip_address, user_agent, notes
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $filingId,
            $userId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $notes
        ]);
    }

    public function buildAdminFilters(array $filters): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "f.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['security_level'])) {
            $where[] = "f.security_level = ?";
            $params[] = $filters['security_level'];
        }

        if (!empty($filters['access_mode'])) {
            $where[] = "f.access_mode = ?";
            $params[] = $filters['access_mode'];
        }

        if (!empty($filters['declared_file_type'])) {
            $where[] = "f.declared_file_type = ?";
            $params[] = $filters['declared_file_type'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "f.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "f.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $where[] = "(
                f.display_name LIKE ? 
                OR f.original_name LIKE ? 
                OR f.keywords LIKE ? 
                OR f.file_code LIKE ?
            )";
            $k = '%' . trim($filters['search']) . '%';
            array_push($params, $k, $k, $k, $k);
        }

        return [implode(' AND ', $where), $params];
    }

    public function countAdminFiles(array $filters): int
    {
        list($where, $params) = $this->buildAdminFilters($filters);
        $stmt = $this->db->prepare("SELECT COUNT(f.rec_id) FROM sys_filing f WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getAdminFiles(array $filters, int $limit = 20, int $offset = 0): array
    {
        list($where, $params) = $this->buildAdminFilters($filters);
        
        $sql = "
            SELECT 
                f.rec_id, f.file_code, f.display_name, f.original_name, f.zip_size,
                f.declared_file_type, f.security_level, f.access_mode, f.status,
                f.uploaded_by, f.created_at, f.updated_at, f.expired_at, f.storage_path,
                u.account_nm as owner_name
            FROM sys_filing f
            LEFT JOIN sysitc_users u ON f.uploaded_by = u.rec_id
            WHERE $where
            ORDER BY f.created_at DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function blockFile(int $filingId, int $adminUserId): array
    {
        $stmt = $this->db->prepare("SELECT status FROM sys_filing WHERE rec_id = ?");
        $stmt->execute([$filingId]);
        $file = $stmt->fetch();
        if (!$file) return ['success' => false, 'message' => 'File not found.'];

        if ($file['status'] === 'blocked') {
            return ['success' => false, 'message' => 'File is already blocked.'];
        }

        // Ideally save previous status. For MVP, we just update to blocked.
        $stmtUp = $this->db->prepare("UPDATE sys_filing SET status = 'blocked', updated_at = NOW() WHERE rec_id = ?");
        if ($stmtUp->execute([$filingId])) {
            $this->logAudit($filingId, $adminUserId, 'admin_block', "File blocked by admin. Previous status: {$file['status']}");
            return ['success' => true, 'message' => 'File blocked successfully.'];
        }
        return ['success' => false, 'message' => 'Failed to block file.'];
    }

    public function unblockFile(int $filingId, int $adminUserId): array
    {
        $stmt = $this->db->prepare("SELECT status FROM sys_filing WHERE rec_id = ?");
        $stmt->execute([$filingId]);
        $file = $stmt->fetch();
        if (!$file) return ['success' => false, 'message' => 'File not found.'];

        if ($file['status'] !== 'blocked') {
            return ['success' => false, 'message' => 'File is not blocked.'];
        }

        $stmtUp = $this->db->prepare("UPDATE sys_filing SET status = 'active', updated_at = NOW() WHERE rec_id = ?");
        if ($stmtUp->execute([$filingId])) {
            $this->logAudit($filingId, $adminUserId, 'admin_unblock', "File unblocked to active status by admin.");
            return ['success' => true, 'message' => 'File unblocked successfully.'];
        }
        return ['success' => false, 'message' => 'Failed to unblock file.'];
    }

    public function restoreFile(int $filingId, int $adminUserId): array
    {
        $stmt = $this->db->prepare("SELECT status FROM sys_filing WHERE rec_id = ?");
        $stmt->execute([$filingId]);
        $file = $stmt->fetch();
        if (!$file) return ['success' => false, 'message' => 'File not found.'];

        $stmtUp = $this->db->prepare("
            UPDATE sys_filing 
            SET status = 'active', deleted_at = NULL, trashed_at = NULL, expired_processed_at = NULL, updated_at = NOW() 
            WHERE rec_id = ?
        ");
        if ($stmtUp->execute([$filingId])) {
            $this->logAudit($filingId, $adminUserId, 'admin_restore', "File restored to active by admin from status: {$file['status']}");
            return ['success' => true, 'message' => 'File restored successfully.'];
        }
        return ['success' => false, 'message' => 'Failed to restore file.'];
    }

    public function permanentDeleteFile(int $filingId, int $adminUserId, string $confirmText): array
    {
        if (trim($confirmText) !== 'DELETE') {
            return ['success' => false, 'message' => 'Confirmation text must be exactly DELETE.'];
        }

        $stmt = $this->db->prepare("SELECT * FROM sys_filing WHERE rec_id = ?");
        $stmt->execute([$filingId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$file) return ['success' => false, 'message' => 'File not found.'];

        if ($file['status'] === 'deleted') {
            return ['success' => false, 'message' => 'File is already permanently deleted.'];
        }

        $remoteSize = $this->storage->getFileSize($file['storage_path']);
        if ($remoteSize > 0) {
            $deletedPhysically = $this->storage->deletePhysicalFile($file['storage_path']);
            if (!$deletedPhysically) {
                $this->logAudit($filingId, $adminUserId, 'admin_permanent_delete_failed', 'Failed to delete physical file from storage.');
                return ['success' => false, 'message' => 'Gagal menghapus file fisik di storage.'];
            }
            $notes = "Physical file deleted and marked as deleted by admin.";
        } else {
            $notes = "Physical file was missing, marked as deleted by admin.";
        }

        $stmtUp = $this->db->prepare("UPDATE sys_filing SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() WHERE rec_id = ?");
        if ($stmtUp->execute([$filingId])) {
            $this->logAudit($filingId, $adminUserId, 'admin_permanent_delete', $notes);
            return ['success' => true, 'message' => 'File berhasil dihapus permanen.'];
        }

        return ['success' => false, 'message' => 'Gagal memperbarui status database.'];
    }

    public function runStorageDiagnostics(int $filingId, int $adminUserId): array
    {
        $stmt = $this->db->prepare("SELECT zip_size, storage_path FROM sys_filing WHERE rec_id = ?");
        $stmt->execute([$filingId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$file) return ['success' => false, 'message' => 'File not found.'];

        $actualSize = $this->storage->getFileSize($file['storage_path']);
        
        $diag = [
            'exists' => $actualSize >= 0,
            'readable' => $actualSize >= 0,
            'size_match' => $actualSize === (int)$file['zip_size'],
            'actual_size' => $actualSize >= 0 ? $actualSize : 0,
            'expected_size' => (int)$file['zip_size'],
            'status' => 'unknown',
            'message' => ''
        ];

        if ($diag['exists']) {
            if ($diag['size_match']) {
                $diag['status'] = 'available';
                $diag['message'] = 'File physical terdeteksi aman dan ukuran sesuai.';
            } else {
                $diag['status'] = 'size_mismatch';
                $diag['message'] = 'File physical terdeteksi tapi ukuran tidak sesuai (Kemungkinan corrupt atau diubah dari luar).';
            }
        } else {
            $diag['status'] = 'missing';
            $diag['message'] = 'File physical hilang atau tidak dapat diakses di FTP.';
        }

        $this->logAudit($filingId, $adminUserId, 'admin_storage_recheck', "Storage diagnostics run. Status: {$diag['status']}");

        return ['success' => true, 'data' => $diag];
    }
}
