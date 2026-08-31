<?php

// File: modules/cbt_ops/filing_system/inspect.php

require_once __DIR__.'/../../../config.php';
require_once __DIR__.'/services/FilingPermissionService.php';
require_once __DIR__.'/services/FilingStorageService.php';
require_once __DIR__.'/services/ZipInspectionService.php';
require_once __DIR__.'/models/FilingSystem.php';

header('Content-Type: application/json');

if (! isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$filingId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($filingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid File ID']);
    exit;
}

$permService = new FilingPermissionService($pdo_run);
$storageService = new FilingStorageService($ftp_config);
$inspectionService = new ZipInspectionService;
$filingModel = new FilingSystem($pdo_run);

try {
    // 1. Fetch metadata & check basic status
    $file = $filingModel->getFileById($filingId);
    if (! $file) {
        throw new Exception('File tidak ditemukan.');
    }

    // Status filtering: only active or archived can be inspected. Regular users cannot inspect trashed/deleted.
    if (! in_array($file['status'], ['active', 'archived'])) {
        if ($file['status'] === 'trashed' && ! $permService->canManage($file, $userId)) {
            throw new Exception('File berada di Sampah. Akses ditolak.');
        }
        if ($file['status'] === 'deleted' || $file['status'] === 'blocked') {
            throw new Exception('Status file tidak valid untuk inspeksi.');
        }
    }

    // 2. Permission View
    if (! $permService->canView($file, $userId)) {
        throw new Exception('Anda tidak memiliki izin untuk melihat isi file ini.');
    }

    // 3. Prepare Local File
    $tmpPath = $storageService->createTemporaryCopy($file['storage_path']);
    if (! $tmpPath || ! file_exists($tmpPath)) {
        throw new Exception('Gagal menarik file dari storage server.');
    }

    // 4. Execute Inspection
    $result = $inspectionService->inspect($tmpPath);

    // Cleanup local temp file immediately
    @unlink($tmpPath);

    if (! $result['success']) {
        $filingModel->logAudit($filingId, $userId, 'zip_inspect_failed', 'ZIP inspection failed: '.implode(', ', $result['warnings']));
        throw new Exception($result['message']);
    }

    // 5. Optional Auto-Update Metadata if inspection brings new accuracy
    // Only update if current data is zero/unknown or if it differs substantially
    $metadataUpdated = false;
    if (
        (int) $file['file_count'] !== (int) $result['file_count'] ||
        (int) $file['total_uncompressed_size'] !== (int) $result['total_uncompressed_size'] ||
        ($file['detected_file_type'] !== $result['detected_file_type'])
    ) {
        // Use a lightweight direct update to avoid triggering massive audit changes, just keep it clean
        $updateStmt = $pdo_run->prepare('
            UPDATE sys_filing 
            SET file_count = ?, total_uncompressed_size = ?, detected_file_type = ?, updated_at = NOW() 
            WHERE rec_id = ?
        ');
        $updateStmt->execute([
            $result['file_count'],
            $result['total_uncompressed_size'],
            $result['detected_file_type'],
            $filingId,
        ]);
        $metadataUpdated = true;
    }

    // 6. Audit Logging
    $auditNote = "ZIP inspected. Files: {$result['file_count']}.";
    if (! empty($result['warnings'])) {
        $auditNote .= ' Warnings: '.implode(', ', $result['warnings']);
    }
    if ($metadataUpdated) {
        $auditNote .= ' (Metadata synced)';
    }
    $filingModel->logAudit($filingId, $userId, 'zip_inspect', $auditNote);

    // 7. Inject standard info back to response for UI reference
    $result['file_info'] = [
        'display_name' => $file['display_name'],
        'file_code' => $file['file_code'],
        'zip_size' => $file['zip_size'],
    ];

    echo json_encode($result);

} catch (Exception $e) {
    // If temp file was left dangling
    if (isset($tmpPath) && file_exists($tmpPath)) {
        @unlink($tmpPath);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
