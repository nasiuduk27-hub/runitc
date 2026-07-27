<?php
// File: modules/cbt_ops/filing_system/permission.php

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/services/FilingPermissionService.php';
require_once __DIR__ . '/models/FilingSystem.php';
require_once __DIR__ . '/models/FilingAccess.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$filingId = (int) ($_POST['filing_id'] ?? $_GET['filing_id'] ?? 0);

if ($filingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid File ID']);
    exit;
}

$permissionService = new FilingPermissionService($pdo_run);
$filingModel = new FilingSystem($pdo_run);
$accessModel = new FilingAccess($pdo_run);

try {
    $file = $filingModel->getFileById($filingId);
    if (!$file) throw new Exception("File tidak ditemukan.");

    switch ($action) {
        case 'get':
            if (!$permissionService->canUpdatePermission($file, $userId)) {
                throw new Exception("Permission denied.");
            }
            $rules = $accessModel->getRulesByFilingId($filingId);
            echo json_encode([
                'success' => true,
                'data' => [
                    'access_mode' => $file['access_mode'],
                    'security_level' => $file['security_level'],
                    'rules' => $rules,
                    'access_options' => $accessModel->getAccessOptions()
                ],
                'is_admin' => $permissionService->isAdmin($userId)
            ]);
            break;

        case 'save_all':
            if ($file['status'] === 'deleted' || $file['status'] === 'trashed' || $file['status'] === 'blocked') {
                throw new Exception("File dengan status {$file['status']} tidak dapat diubah permission-nya.");
            }
            if (!$permissionService->canUpdatePermission($file, $userId)) {
                $accessModel->logAudit($filingId, $userId, 'permission_update_denied', 'Permission denied');
                throw new Exception("Anda tidak memiliki izin mengatur permission file ini.");
            }

            $accessMode = $_POST['access_mode'] ?? 'private';
            $validModes = ['private', 'custom', 'public_internal', 'share_link'];
            if (!in_array($accessMode, $validModes)) {
                throw new Exception("Access Mode tidak valid.");
            }

            $rawRules = isset($_POST['rules']) && is_string($_POST['rules']) ? json_decode($_POST['rules'], true) : [];
            if (!is_array($rawRules)) $rawRules = [];

            $normalizedRules = [];
            $seenPairs = [];
            $confidentialShareRemoved = false;

            foreach ($rawRules as $r) {
                $norm = $permissionService->normalizePermissionRule($r, $file, $userId);
                
                if (empty($norm['access_type']) || (empty($norm['access_value']) && $norm['access_value'] !== '0')) {
                    continue; // Skip invalid
                }

                if (!$accessModel->validateAccessValue((string)$norm['access_type'], (string)$norm['access_value'])) {
                    throw new Exception("Nilai akses {$norm['access_type']} tidak valid atau tidak terdaftar.");
                }

                $pairKey = $norm['access_type'] . '_' . $norm['access_value'];
                if (isset($seenPairs[$pairKey])) {
                    continue; // Skip duplicate
                }
                $seenPairs[$pairKey] = true;

                // Check if confidential override happened
                if (!empty($r['can_share']) && $norm['can_share'] === 0 && $file['security_level'] === 'confidential') {
                    $confidentialShareRemoved = true;
                }

                $normalizedRules[] = $norm;
            }

            $pdo_run->beginTransaction();

            // Update mode in sys_filing
            $stmtUpdate = $pdo_run->prepare("UPDATE sys_filing SET access_mode = ?, updated_at = NOW() WHERE rec_id = ?");
            $stmtUpdate->execute([$accessMode, $filingId]);

            // Replace rules
            $accessModel->replaceRules($filingId, $normalizedRules, $userId);

            // Audit Logic
            $notes = "Mode changed to {$accessMode}. Updated " . count($normalizedRules) . " rules.";
            $accessModel->logAudit($filingId, $userId, 'permission_update', $notes);

            if ($confidentialShareRemoved) {
                $accessModel->logAudit($filingId, $userId, 'permission_confidential_share_removed', 'can_share forced to 0 due to confidential level');
            }

            $pdo_run->commit();
            echo json_encode(['success' => true, 'message' => 'Hak akses berhasil diperbarui.']);
            break;

        default:
            throw new Exception("Action tidak dikenali.");
    }
} catch (Exception $e) {
    if (isset($pdo_run) && $pdo_run->inTransaction()) {
        $pdo_run->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
