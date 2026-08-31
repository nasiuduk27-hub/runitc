<?php

// File: modules/cbt_ops/filing_system/info.php

require_once __DIR__.'/../../../config.php';
require_once __DIR__.'/services/FilingPermissionService.php';
require_once __DIR__.'/models/FilingSystem.php';
require_once __DIR__.'/models/FilingAccess.php';
require_once __DIR__.'/models/FilingShare.php';
require_once __DIR__.'/models/FilingAudit.php';

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
$filingModel = new FilingSystem($pdo_run);
$accessModel = new FilingAccess($pdo_run);
$shareModel = new FilingShare($pdo_run);
$auditModel = new FilingAudit($pdo_run);

$isAdmin = $permService->isAdmin($userId);

try {
    // 1. Ambil Data File
    $file = $filingModel->getFileById($filingId);
    if (! $file) {
        throw new Exception('File tidak ditemukan.');
    }

    // 2. Cek Permission canView
    if (! $permService->canView($file, $userId)) {
        throw new Exception('Anda tidak memiliki izin untuk melihat detail file ini.');
    }

    // 3. Ambil Owner Info
    $owner = $filingModel->getFileOwnerInfo($file['uploaded_by']);

    $permissionResult = $permService->getPermissionResult($file, $userId);
    $canSeeFullInfo = $isAdmin || (int) $file['uploaded_by'] === $userId || $permissionResult['can_manage'];

    // 4. Summaries hanya untuk owner/admin/manager.
    $permissions = $canSeeFullInfo ? $accessModel->getPermissionSummary($filingId) : [];
    $shares = $canSeeFullInfo ? $shareModel->getShareSummary($filingId) : ['stats' => ['active_links' => 0, 'total_access' => 0], 'details' => []];
    $audits = $canSeeFullInfo ? $auditModel->getRecentAuditByFile($filingId, 10) : [];

    // 5. Build Response (Protect sensitive data)
    $response = [
        'success' => true,
        'is_admin' => $isAdmin,
        'can_see_full_info' => $canSeeFullInfo,
        'file' => [
            'rec_id' => $file['rec_id'],
            'display_name' => $file['display_name'],
            'original_name' => $file['original_name'],
            'zip_size' => $file['zip_size'],
            'formatted_size' => $filingModel->formatFileSize($file['zip_size'] ?: 0),
            'file_count' => $file['file_count'],
            'detected_file_type' => $file['detected_file_type'],
            'status' => $file['status'],
            'status_label' => $filingModel->formatStatusLabel($file['status']),
            'security_level' => $file['security_level'],
            'security_label' => $filingModel->formatSecurityLabel($file['security_level']),
            'created_at' => $file['created_at'],
            'updated_at' => $file['updated_at'],
            'owner_name' => $owner['account_nm'] ?? 'Unknown',
            'owner_alias' => $owner['alias_nm'] ?? '',
        ],
        'permissions' => $permissions,
        'shares' => $shares,
        'audits' => array_map(function ($a) use ($auditModel, $isAdmin) {
            return [
                'created_at' => $a['created_at'],
                'action' => $a['action'],
                'action_label' => $auditModel->formatActionLabel($a['action']),
                'user_name' => $a['user_id'] ? ($a['user_name'] ?? 'Unknown') : 'SYSTEM/CRON',
                'notes' => $a['notes'],
                'ip_address' => $isAdmin ? ($a['ip_address'] ?? '-') : null,
                'user_agent' => $isAdmin ? ($a['user_agent'] ?? '-') : null,
            ];
        }, $audits),
    ];

    if ($canSeeFullInfo) {
        $response['file'] += [
            'file_code' => $file['file_code'],
            'total_uncompressed_size' => $file['total_uncompressed_size'],
            'formatted_uncompressed_size' => $filingModel->formatFileSize($file['total_uncompressed_size'] ?: 0),
            'declared_file_type' => $file['declared_file_type'],
            'access_mode' => $file['access_mode'],
            'is_share_enabled' => (bool) $file['is_share_enabled'],
            'expired_at' => $file['expired_at'],
            'expired_action' => $file['expired_action'],
            'expired_processed_at' => $file['expired_processed_at'],
            'notes' => $file['notes'],
            'keywords' => $file['keywords'],
        ];
    }

    // Admin-only data
    if ($isAdmin) {
        $response['file']['storage_root'] = $file['storage_root'];
        $response['file']['storage_dir'] = $file['storage_dir'];
        $response['file']['storage_name'] = $file['storage_name'];
        $response['file']['storage_path'] = $file['storage_path'];
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
