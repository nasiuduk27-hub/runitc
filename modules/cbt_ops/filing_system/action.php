<?php
// File: modules/cbt_ops/filing_system/action.php

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/services/FilingPermissionService.php';
require_once __DIR__ . '/services/FilingStorageService.php';
require_once __DIR__ . '/models/FilingSystem.php'; // Included FileModel

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$filingId = (int) ($_POST['filing_id'] ?? ($_POST['file_id'] ?? ($_POST['id'] ?? 0)));

if ($action !== 'bulk' && $filingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid File ID']);
    exit;
}

$permissionService = new FilingPermissionService($pdo_run);
$storageService = new FilingStorageService($ftp_config);
$filingModel = new FilingSystem($pdo_run);

function logAudit(PDO $db, int $filingId, int $userId, string $action, string $notes) {
    $stmt = $db->prepare("INSERT INTO sys_filing_audit (filing_id, user_id, action, ip_address, user_agent, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $filingId, $userId, $action, 
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 
        $notes
    ]);
}

try {
    // 1. Ambil Data File untuk aksi single. Bulk mengambil file setelah validasi daftar ID.
    $file = null;
    if ($action !== 'bulk') {
        $file = $filingModel->getFileById($filingId);

        if (!$file) throw new Exception("File tidak ditemukan.");
    }

    // 2. Routing Aksi
    switch ($action) {
        
        case 'get_file_info':
            // Endpoint to prefill edit modal
            if (!$permissionService->canManage($file, $userId)) {
                throw new Exception("Permission denied.");
            }
            echo json_encode([
                'success' => true,
                'data' => [
                    'display_name' => $file['display_name'],
                    'declared_file_type' => $file['declared_file_type'],
                    'security_level' => $file['security_level'],
                    'keywords' => $file['keywords'],
                    'notes' => $file['notes'],
                    'expired_at' => $file['expired_at'],
                    'expired_action' => $file['expired_action']
                ],
                'file_types' => $filingModel->getActiveFileTypes(),
                'is_admin' => $permissionService->isAdmin($userId)
            ]);
            break;

        case 'update_metadata':
            if ($file['status'] === 'deleted' || $file['status'] === 'blocked' || $file['status'] === 'trashed') {
                throw new Exception("File dengan status {$file['status']} tidak dapat diedit. Restore file terlebih dahulu.");
            }
            if (!$permissionService->canManage($file, $userId)) {
                logAudit($pdo_run, $filingId, $userId, 'metadata_update_denied', 'Permission denied');
                throw new Exception("Anda tidak memiliki izin mengedit file ini.");
            }

            // Input Validation
            $displayName = trim($_POST['display_name'] ?? '');
            if (empty($displayName)) throw new Exception("Nama Tampilan wajib diisi.");
            if (strlen($displayName) > 255) throw new Exception("Nama Tampilan maksimal 255 karakter.");
            if (preg_match('/[\/\\\\]|\.\./', $displayName)) {
                throw new Exception("Format Nama Tampilan tidak aman (mengandung karakter slash atau path traversal).");
            }

            $secLevel = $_POST['security_level'] ?? 'normal';
            if (!in_array($secLevel, ['normal', 'restricted', 'confidential'])) {
                $secLevel = 'normal';
            }

            $expEnabled = !empty($_POST['expired_at']);
            $expAt = $expEnabled ? date('Y-m-d H:i:s', strtotime($_POST['expired_at'])) : null;
            $expAction = $_POST['expired_action'] ?? 'trash';
            
            if ($expAction === 'delete' && !$permissionService->isAdmin($userId)) {
                throw new Exception("Hanya admin yang dapat memilih aksi Hapus Permanen.");
            }

            $updateData = [
                'display_name' => $displayName,
                'declared_file_type' => $_POST['declared_file_type'] ?? 'mixed',
                'security_level' => $secLevel,
                'keywords' => $_POST['keywords'] ?? '',
                'notes' => $_POST['notes'] ?? '',
                'expired_at' => $expAt,
                'expired_action' => $expAction
            ];

            // Handle logic reset processed_at if expiry is extended/re-enabled
            if ($expEnabled && $file['expired_at'] !== $expAt && $file['status'] === 'active') {
                $updateData['expired_processed_at'] = null;
            }
            if (!$expEnabled) {
                $updateData['expired_processed_at'] = null; // Reset
            }

            $pdo_run->beginTransaction();

            $updated = $filingModel->updateMetadata($filingId, $updateData, $userId);
            if (!$updated) throw new Exception("Gagal menyimpan perubahan ke database.");

            // Construct Audit Notes based on what changed
            $changes = [];
            if ($file['display_name'] !== $updateData['display_name']) {
                $changes[] = "Renamed from '{$file['display_name']}' to '{$updateData['display_name']}'";
                logAudit($pdo_run, $filingId, $userId, 'rename', end($changes));
            }
            if ($file['security_level'] !== $updateData['security_level']) {
                $changes[] = "Security changed from '{$file['security_level']}' to '{$updateData['security_level']}'";
                logAudit($pdo_run, $filingId, $userId, 'security_update', end($changes));

                // Auto revoke shares if changed to confidential
                if ($updateData['security_level'] === 'confidential') {
                    $stmtRevoke = $pdo_run->prepare("UPDATE sys_filing_share SET is_active = 0, revoked_at = NOW() WHERE filing_id = ? AND is_active = 1");
                    $stmtRevoke->execute([$filingId]);
                    if ($stmtRevoke->rowCount() > 0) {
                        logAudit($pdo_run, $filingId, $userId, 'share_auto_revoked_confidential', "Revoked {$stmtRevoke->rowCount()} shares due to confidential level.");
                    }
                }
            }
            if ($file['expired_at'] !== $updateData['expired_at']) {
                if ($updateData['expired_at'] === null) {
                    $changes[] = "Expiry disabled";
                    logAudit($pdo_run, $filingId, $userId, 'expiry_disable', end($changes));
                } else if ($file['expired_at'] === null) {
                    $changes[] = "Expiry set to '{$updateData['expired_at']}'";
                    logAudit($pdo_run, $filingId, $userId, 'expiry_set', end($changes));
                } else {
                    $changes[] = "Expiry updated from '{$file['expired_at']}' to '{$updateData['expired_at']}'";
                    // If extended (new > old)
                    if (strtotime($updateData['expired_at']) > strtotime($file['expired_at'])) {
                        logAudit($pdo_run, $filingId, $userId, 'expiry_extend', end($changes));
                    } else {
                        logAudit($pdo_run, $filingId, $userId, 'expiry_update', end($changes));
                    }
                }
            }

            if (empty($changes)) {
                // If other metadata (notes, keywords, type) changed
                logAudit($pdo_run, $filingId, $userId, 'metadata_update', 'Updated file metadata (notes/keywords/type)');
            }

            $pdo_run->commit();
            echo json_encode(['success' => true, 'message' => 'Info file berhasil diperbarui.']);
            break;

        case 'bulk':
            $bulkAction = $_POST['bulk_action'] ?? '';
            $rawIds = $_POST['filing_ids'] ?? [];
            if (!is_array($rawIds)) throw new Exception("Format ID tidak valid.");
            
            $ids = array_unique(array_filter(array_map('intval', $rawIds)));
            if (empty($ids)) throw new Exception("Tidak ada file yang dipilih.");
            if (count($ids) > 50) throw new Exception("Maksimal 50 file untuk aksi massal.");

            $validActions = ['archive', 'move_trash', 'restore', 'permanent_delete'];
            if (!in_array($bulkAction, $validActions)) throw new Exception("Aksi massal tidak valid.");

            $files = $filingModel->getFilesByIds($ids);

            $stats = [
                'success' => true,
                'message' => 'Proses massal selesai.',
                'total_requested' => count($ids),
                'processed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'items' => []
            ];

            foreach ($ids as $id) {
                $itemRes = ['filing_id' => $id, 'status' => 'skipped', 'message' => ''];
                
                try {
                    $pdo_run->beginTransaction();
                    
                    if (!isset($files[$id])) {
                        $itemRes['message'] = 'File tidak ditemukan.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;
                        $pdo_run->rollBack();
                        continue;
                    }
                    
                    $currFile = $files[$id];
                    
                    if (!$permissionService->canManage($currFile, $userId)) {
                        logAudit($pdo_run, $id, $userId, 'bulk_action_denied', 'Permission denied for bulk ' . $bulkAction);
                        $itemRes['message'] = 'Permission denied.';
                        $stats['skipped']++;
                        $stats['items'][] = $itemRes;
                        $pdo_run->commit(); // Commit the audit log
                        continue;
                    }

                    // Process specific action
                    if ($bulkAction === 'archive') {
                        if ($currFile['status'] !== 'active') {
                            $itemRes['message'] = 'Status tidak valid untuk arsip.';
                            $stats['skipped']++;
                            $stats['items'][] = $itemRes;
                            $pdo_run->rollBack();
                            continue;
                        }
                        $stmt = $pdo_run->prepare("UPDATE sys_filing SET status = 'archived' WHERE rec_id = ?");
                        $stmt->execute([$id]);
                        logAudit($pdo_run, $id, $userId, 'bulk_archive', 'Bulk archived by user');
                        $itemRes['status'] = 'success';
                        $itemRes['message'] = 'Archived.';
                    } 
                    elseif ($bulkAction === 'move_trash') {
                        if ($currFile['status'] !== 'active' && $currFile['status'] !== 'archived') {
                            $itemRes['message'] = 'Status tidak valid untuk trash.';
                            $stats['skipped']++;
                            $stats['items'][] = $itemRes;
                            $pdo_run->rollBack();
                            continue;
                        }
                        $stmt = $pdo_run->prepare("UPDATE sys_filing SET status = 'trashed', deleted_at = NOW() WHERE rec_id = ?");
                        $stmt->execute([$id]);
                        logAudit($pdo_run, $id, $userId, 'bulk_move_trash', 'Bulk moved to trash');
                        $itemRes['status'] = 'success';
                        $itemRes['message'] = 'Moved to trash.';
                    }
                    elseif ($bulkAction === 'restore') {
                        if ($currFile['status'] !== 'trashed' && $currFile['status'] !== 'archived') {
                            $itemRes['message'] = 'Status tidak valid untuk restore.';
                            $stats['skipped']++;
                            $stats['items'][] = $itemRes;
                            $pdo_run->rollBack();
                            continue;
                        }
                        $stmt = $pdo_run->prepare("UPDATE sys_filing SET status = 'active', deleted_at = NULL, trashed_at = NULL WHERE rec_id = ?");
                        $stmt->execute([$id]);
                        $auditAct = $currFile['status'] === 'trashed' ? 'bulk_restore' : 'bulk_restore_archive';
                        logAudit($pdo_run, $id, $userId, $auditAct, 'Bulk restored by user');
                        $itemRes['status'] = 'success';
                        $itemRes['message'] = 'Restored.';
                    }
                    elseif ($bulkAction === 'permanent_delete') {
                        if (!$permissionService->isAdmin($userId) && !$permissionService->canManage($currFile, $userId)) {
                            logAudit($pdo_run, $id, $userId, 'bulk_action_denied', 'Permission denied for permanent delete');
                            $itemRes['message'] = 'Permission denied.';
                            $stats['skipped']++;
                            $stats['items'][] = $itemRes;
                            $pdo_run->commit();
                            continue;
                        }
                        if ($currFile['status'] !== 'trashed' && $currFile['status'] !== 'deleted') {
                            $itemRes['message'] = 'Status tidak valid.';
                            $stats['skipped']++;
                            $stats['items'][] = $itemRes;
                            $pdo_run->rollBack();
                            continue;
                        }
                        
                        $deletedPhysically = $storageService->deletePhysicalFile($currFile['storage_path']);
                        $stmt = $pdo_run->prepare("UPDATE sys_filing SET status = 'deleted', deleted_at = NOW() WHERE rec_id = ?");
                        $stmt->execute([$id]);
                        logAudit($pdo_run, $id, $userId, 'bulk_permanent_delete', 'Bulk permanently deleted');
                        $itemRes['status'] = 'success';
                        $itemRes['message'] = 'Permanently deleted.';
                    }

                    if ($itemRes['status'] === 'success') {
                        $stats['processed']++;
                        $pdo_run->commit();
                    }
                    $stats['items'][] = $itemRes;
                    
                } catch (Exception $e) {
                    if ($pdo_run->inTransaction()) $pdo_run->rollBack();
                    
                    try {
                        logAudit($pdo_run, $id, $userId, 'bulk_action_failed', 'Error: ' . substr($e->getMessage(), 0, 100));
                    } catch (Exception $e2) {}

                    $itemRes['status'] = 'failed';
                    $itemRes['message'] = $e->getMessage();
                    $stats['failed']++;
                    $stats['items'][] = $itemRes;
                }
            }

            echo json_encode($stats);
            break;

        case 'move_trash':
            if ($file['status'] !== 'active' && $file['status'] !== 'archived') {
                throw new Exception("File tidak dalam status active/archived.");
            }
            if (!$permissionService->canManage($file, $userId)) {
                logAudit($pdo_run, $filingId, $userId, 'trash_denied', 'Permission denied');
                throw new Exception("Anda tidak memiliki izin untuk memindahkan file ini ke sampah.");
            }
            
            $stmt = $pdo_run->prepare("UPDATE sys_filing SET status = 'trashed', deleted_at = NOW() WHERE rec_id = ?");
            $stmt->execute([$filingId]);
            logAudit($pdo_run, $filingId, $userId, 'move_trash', "Moved to trash");
            echo json_encode(['success' => true, 'message' => 'File berhasil dipindahkan ke Sampah.']);
            break;

        case 'restore':
            if ($file['status'] !== 'trashed') {
                throw new Exception("File tidak berada di dalam Sampah.");
            }
            if (!$permissionService->canManage($file, $userId)) {
                logAudit($pdo_run, $filingId, $userId, 'restore_denied', 'Permission denied');
                throw new Exception("Anda tidak memiliki izin untuk me-restore file ini.");
            }

            $stmt = $pdo_run->prepare("UPDATE sys_filing SET status = 'active', deleted_at = NULL, trashed_at = NULL WHERE rec_id = ?");
            $stmt->execute([$filingId]);
            logAudit($pdo_run, $filingId, $userId, 'restore', "Restored from trash");
            echo json_encode(['success' => true, 'message' => 'File berhasil dikembalikan ke status Aktif.']);
            break;

        case 'archive':
            if ($file['status'] !== 'active') {
                throw new Exception("Hanya file aktif yang bisa diarsipkan.");
            }
            if (!$permissionService->canManage($file, $userId)) {
                logAudit($pdo_run, $filingId, $userId, 'archive_denied', 'Permission denied');
                throw new Exception("Anda tidak memiliki izin untuk mengarsipkan file ini.");
            }

            $stmt = $pdo_run->prepare("UPDATE sys_filing SET status = 'archived' WHERE rec_id = ?");
            $stmt->execute([$filingId]);
            logAudit($pdo_run, $filingId, $userId, 'archive', "Archived file");
            echo json_encode(['success' => true, 'message' => 'File berhasil diarsipkan.']);
            break;

        case 'restore_archive':
            if ($file['status'] !== 'archived') {
                throw new Exception("File tidak dalam status archived.");
            }
            if (!$permissionService->canManage($file, $userId)) {
                logAudit($pdo_run, $filingId, $userId, 'restore_denied', 'Permission denied for archive restore');
                throw new Exception("Anda tidak memiliki izin untuk me-restore arsip ini.");
            }

            $stmt = $pdo_run->prepare("UPDATE sys_filing SET status = 'active' WHERE rec_id = ?");
            $stmt->execute([$filingId]);
            logAudit($pdo_run, $filingId, $userId, 'restore_archive', "Restored from archive");
            echo json_encode(['success' => true, 'message' => 'Arsip berhasil diaktifkan kembali.']);
            break;

        case 'permanent_delete':
            if ($file['status'] !== 'trashed' && $file['status'] !== 'deleted') {
                throw new Exception("Hanya file di Sampah yang bisa dihapus permanen.");
            }
            if (!$permissionService->isAdmin($userId) && !$permissionService->canManage($file, $userId)) {
                logAudit($pdo_run, $filingId, $userId, 'permanent_delete_denied', 'Permission denied');
                throw new Exception("Anda tidak memiliki izin untuk menghapus permanen file ini.");
            }

            // Hapus File Fisik
            $deletedPhysically = $storageService->deletePhysicalFile($file['storage_path']);
            if (!$deletedPhysically) {
                // Catat log tapi mungkin tetap terus hapus record DB jika FTP gagal tapi file emang ga ada
                // Tapi aman nya kita pastikan aja
                error_log("Failed to delete physical file: " . $file['storage_path']);
            }

            $stmt = $pdo_run->prepare("UPDATE sys_filing SET status = 'deleted', deleted_at = NOW() WHERE rec_id = ?");
            $stmt->execute([$filingId]);
            logAudit($pdo_run, $filingId, $userId, 'permanent_delete', "Permanently deleted from system");
            
            echo json_encode(['success' => true, 'message' => 'File berhasil dihapus secara permanen.']);
            break;

        default:
            throw new Exception("Action tidak valid.");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
