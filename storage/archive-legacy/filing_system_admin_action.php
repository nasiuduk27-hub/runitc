<?php

// File: modules/cbt_ops/filing_system/admin_action.php

require_once __DIR__.'/../../../config.php';
require_once __DIR__.'/services/FilingStorageService.php';
require_once __DIR__.'/services/FilingPermissionService.php';
require_once __DIR__.'/services/FilingAdminService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (! isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$filingId = (int) ($_POST['filing_id'] ?? 0);

if ($filingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid File ID']);
    exit;
}

// TODO: Validate CSRF token here if project helper exists

$storageService = new FilingStorageService($ftp_config);
$permissionService = new FilingPermissionService($pdo_run);
$adminService = new FilingAdminService($pdo_run, $storageService, $permissionService);

if (! $adminService->isFilingSystemAdmin($userId)) {
    echo json_encode(['success' => false, 'message' => 'Access Denied: Admin only']);
    exit;
}

try {
    switch ($action) {
        case 'block':
            $res = $adminService->blockFile($filingId, $userId);
            break;
        case 'unblock':
            $res = $adminService->unblockFile($filingId, $userId);
            break;
        case 'restore':
            $res = $adminService->restoreFile($filingId, $userId);
            break;
        case 'permanent_delete':
            $confirmText = $_POST['confirm_text'] ?? '';
            $res = $adminService->permanentDeleteFile($filingId, $userId, $confirmText);
            break;
        case 'diagnose':
            $res = $adminService->runStorageDiagnostics($filingId, $userId);
            break;
        default:
            throw new Exception('Unknown admin action.');
    }
    echo json_encode($res);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
