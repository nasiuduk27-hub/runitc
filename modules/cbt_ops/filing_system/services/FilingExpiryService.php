<?php
// File: modules/cbt_ops/filing_system/services/FilingExpiryService.php

require_once __DIR__ . '/FilingStorageService.php';

class FilingExpiryService
{
    private PDO $db;
    private ?FilingStorageService $storage;

    public function __construct(PDO $pdoRun, ?FilingStorageService $storage = null)
    {
        $this->db = $pdoRun;
        $this->storage = $storage;
    }

    /**
     * Mencari file yang sudah melewati expired_at dan belum diproses.
     */
    public function findExpiredFiles(int $limit = 100): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM sys_filing
            WHERE status = 'active'
              AND expired_at IS NOT NULL
              AND expired_at <= NOW()
              AND expired_processed_at IS NULL
            ORDER BY expired_at ASC
            LIMIT ?
        ");
        
        // PDO bindValue untuk integer limit
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mencatat aksi ke audit log. Menggunakan system user (user_id = NULL).
     */
    public function logExpiryAudit(int $filingId, string $action, string $notes): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO sys_filing_audit (
                filing_id, user_id, action, ip_address, user_agent, notes
            ) VALUES (?, NULL, ?, NULL, 'cron', ?)
        ");
        $stmt->execute([$filingId, $action, $notes]);
    }

    /**
     * Proses Action: None
     */
    public function applyNone(array $file): bool
    {
        $stmt = $this->db->prepare("
            UPDATE sys_filing 
            SET status = 'expired', expired_processed_at = NOW() 
            WHERE rec_id = ? AND expired_processed_at IS NULL
        ");
        $stmt->execute([$file['rec_id']]);
        
        if ($stmt->rowCount() > 0) {
            $this->logExpiryAudit($file['rec_id'], 'expiry_none', "File automatically expired.");
            return true;
        }
        return false;
    }

    /**
     * Proses Action: Archive
     */
    public function applyArchive(array $file): bool
    {
        $stmt = $this->db->prepare("
            UPDATE sys_filing 
            SET status = 'archived', expired_processed_at = NOW() 
            WHERE rec_id = ? AND expired_processed_at IS NULL
        ");
        $stmt->execute([$file['rec_id']]);
        
        if ($stmt->rowCount() > 0) {
            $this->logExpiryAudit($file['rec_id'], 'expiry_archive', "File automatically archived due to expiry.");
            return true;
        }
        return false;
    }

    /**
     * Proses Action: Trash
     */
    public function applyTrash(array $file): bool
    {
        $stmt = $this->db->prepare("
            UPDATE sys_filing 
            SET status = 'trashed', deleted_at = NOW(), expired_processed_at = NOW() 
            WHERE rec_id = ? AND expired_processed_at IS NULL
        ");
        $stmt->execute([$file['rec_id']]);
        
        if ($stmt->rowCount() > 0) {
            $this->logExpiryAudit($file['rec_id'], 'expiry_trash', "File automatically moved to trash due to expiry.");
            return true;
        }
        return false;
    }

    /**
     * Proses Action: Delete (MVP Fallback)
     */
    public function applyDeletePolicy(array $file): bool
    {
        // Untuk MVP, auto-hard-delete dinonaktifkan. Fallback ke Trash.
        $stmt = $this->db->prepare("
            UPDATE sys_filing 
            SET status = 'trashed', deleted_at = NOW(), expired_processed_at = NOW() 
            WHERE rec_id = ? AND expired_processed_at IS NULL
        ");
        $stmt->execute([$file['rec_id']]);
        
        if ($stmt->rowCount() > 0) {
            $this->logExpiryAudit($file['rec_id'], 'expiry_delete_skipped', "Auto hard-delete is disabled for MVP. File moved to trash instead.");
            return true;
        }
        return false;
    }

    /**
     * Memproses satu file dengan database transaction.
     */
    public function processSingleFile(array $file): array
    {
        try {
            $this->db->beginTransaction();

            $action = $file['expired_action'] ?? 'trash';
            $success = false;
            $statusStr = '';

            switch ($action) {
                case 'none':
                    $success = $this->applyNone($file);
                    $statusStr = 'expired';
                    break;
                case 'archive':
                    $success = $this->applyArchive($file);
                    $statusStr = 'archived';
                    break;
                case 'trash':
                    $success = $this->applyTrash($file);
                    $statusStr = 'trashed';
                    break;
                case 'delete':
                    $success = $this->applyDeletePolicy($file);
                    $statusStr = 'trashed'; // Fallback
                    break;
                default:
                    // Fallback to trash if unknown action
                    $success = $this->applyTrash($file);
                    $statusStr = 'trashed';
                    break;
            }

            if ($success) {
                $this->db->commit();
                return ['success' => true, 'status' => $statusStr];
            } else {
                $this->db->rollBack();
                // Gagal karena concurrency atau row tidak ditemukan (kemungkinan diproses cron lain)
                return ['success' => false, 'status' => 'skipped'];
            }

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            // Coba log error di luar transaksi yang gagal
            try {
                $this->logExpiryAudit($file['rec_id'], 'expiry_error', "Processing error: " . $e->getMessage());
            } catch (Throwable $ignore) {}

            return ['success' => false, 'status' => 'error'];
        }
    }

    /**
     * Entry point utama cron job.
     */
    public function processExpiredFiles(int $limit = 100): array
    {
        $files = $this->findExpiredFiles($limit);
        
        $stats = [
            'total_scanned' => count($files),
            'total_processed' => 0,
            'total_archived' => 0,
            'total_trashed' => 0,
            'total_expired' => 0,
            'total_skipped' => 0,
            'total_error' => 0
        ];

        foreach ($files as $file) {
            $result = $this->processSingleFile($file);
            
            if ($result['success']) {
                $stats['total_processed']++;
                switch ($result['status']) {
                    case 'archived': $stats['total_archived']++; break;
                    case 'trashed': $stats['total_trashed']++; break;
                    case 'expired': $stats['total_expired']++; break;
                }
            } else {
                if ($result['status'] === 'skipped') {
                    $stats['total_skipped']++;
                } else {
                    $stats['total_error']++;
                }
            }
        }

        return $stats;
    }

    public function purgeOldTrash(int $retentionDays = 30, int $limit = 100): array
    {
        $retentionDays = max(1, $retentionDays);

        $stmt = $this->db->prepare("
            SELECT rec_id, storage_path
            FROM sys_filing
            WHERE status = 'trashed'
              AND deleted_at IS NOT NULL
              AND deleted_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY deleted_at ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $retentionDays, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats = [
            'total_scanned' => count($files),
            'total_purged' => 0,
            'total_failed' => 0,
            'retention_days' => $retentionDays,
        ];

        foreach ($files as $file) {
            try {
                $deletedPhysically = true;
                if ($this->storage !== null) {
                    $remoteSize = $this->storage->getFileSize((string) $file['storage_path']);
                    $deletedPhysically = $remoteSize <= 0 || $this->storage->deletePhysicalFile((string) $file['storage_path']);
                }

                if (!$deletedPhysically) {
                    $this->logExpiryAudit((int) $file['rec_id'], 'trash_auto_purge_failed', 'Failed to delete physical file from storage.');
                    $stats['total_failed']++;
                    continue;
                }

                $up = $this->db->prepare("UPDATE sys_filing SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() WHERE rec_id = ? AND status = 'trashed'");
                $up->execute([(int) $file['rec_id']]);

                if ($up->rowCount() > 0) {
                    $this->logExpiryAudit((int) $file['rec_id'], 'trash_auto_purge', "Trash auto-purged after {$retentionDays} days.");
                    $stats['total_purged']++;
                }
            } catch (Throwable $e) {
                $stats['total_failed']++;
                try {
                    $this->logExpiryAudit((int) $file['rec_id'], 'trash_auto_purge_error', 'Error: ' . $e->getMessage());
                } catch (Throwable $ignore) {}
            }
        }

        return $stats;
    }
}
