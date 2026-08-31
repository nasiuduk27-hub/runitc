<?php

// File: modules/cbt_ops/filing_system/share.php

require_once __DIR__.'/../../../config.php';
require_once __DIR__.'/services/FilingPermissionService.php';
require_once __DIR__.'/services/ShareCodeService.php';
require_once __DIR__.'/models/FilingShare.php';

header('Content-Type: application/json');

if (! isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$permissionService = new FilingPermissionService($pdo_run);
$shareService = new ShareCodeService;
$shareModel = new FilingShare($pdo_run);

try {
    switch ($action) {
        case 'create':
            $filingId = (int) ($_POST['filing_id'] ?? 0);

            // Validate File & Status
            $stmt = $pdo_run->prepare("SELECT * FROM sys_filing WHERE rec_id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$filingId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $file) {
                throw new Exception('File tidak ditemukan atau tidak aktif.');
            }

            // Validation Permission
            if (! $permissionService->canShare($file, $userId)) {
                $shareModel->logAudit($filingId, $userId, 'share_create_denied', 'Permission denied');
                throw new Exception('Anda tidak memiliki izin untuk membagikan file ini.');
            }

            // Security Level Constraint
            if ($file['security_level'] === 'confidential') {
                throw new Exception('File confidential tidak dapat dibagikan via Share Code.');
            }

            $expiredAt = ! empty($_POST['expired_at']) ? $_POST['expired_at'] : null;
            if ($file['security_level'] === 'restricted' && empty($expiredAt)) {
                throw new Exception('File restricted wajib memiliki Expired Date saat dibagikan.');
            }

            // Generate
            $rawCode = $shareService->generateShareCode();
            $hash = $shareService->hashShareCode($rawCode);
            $preview = $shareService->maskShareCode($rawCode);

            $reqPassword = ! empty($_POST['requires_password']) && $_POST['requires_password'] == '1';
            $passwordHash = null;
            if ($reqPassword && ! empty($_POST['share_password'])) {
                $passwordHash = password_hash($_POST['share_password'], PASSWORD_DEFAULT);
            }

            $data = [
                'filing_id' => $filingId,
                'share_code_preview' => $preview,
                'share_code_hash' => $hash,
                'created_by' => $userId,
                'expired_at' => $expiredAt,
                'max_access' => ! empty($_POST['max_access']) ? (int) $_POST['max_access'] : null,
                'requires_password' => $reqPassword,
                'password_hash' => $passwordHash,
                'allow_download' => isset($_POST['allow_download']) ? (int) $_POST['allow_download'] : 1,
            ];

            $res = $shareModel->createShare($data);
            if (! $res['success']) {
                throw new Exception($res['message']);
            }

            // Set sys_filing.is_share_enabled
            $pdo_run->prepare('UPDATE sys_filing SET is_share_enabled = 1 WHERE rec_id = ?')->execute([$filingId]);

            $shareModel->logAudit($filingId, $userId, 'create_share', "Created share: $preview");

            echo json_encode([
                'success' => true,
                'message' => 'Share Code berhasil dibuat.',
                'raw_code' => $rawCode, // Only returned once!
            ]);
            break;

        case 'list':
            $filingId = (int) ($_GET['filing_id'] ?? 0);
            $shares = $shareModel->getSharesByFilingId($filingId);
            echo json_encode(['success' => true, 'data' => $shares]);
            break;

        case 'revoke':
            $shareId = (int) ($_POST['share_id'] ?? 0);
            // Verify ownership of the share or file
            $stmt = $pdo_run->prepare('
                SELECT s.filing_id, f.uploaded_by 
                FROM sys_filing_share s 
                JOIN sys_filing f ON s.filing_id = f.rec_id 
                WHERE s.rec_id = ?
            ');
            $stmt->execute([$shareId]);
            $share = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $share) {
                throw new Exception('Share tidak ditemukan.');
            }

            // Re-fetch file to check manage/share permissions
            $stmt = $pdo_run->prepare('SELECT * FROM sys_filing WHERE rec_id = ?');
            $stmt->execute([$share['filing_id']]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $permissionService->canManage($file, $userId) && ! $permissionService->canShare($file, $userId)) {
                throw new Exception('Anda tidak berhak me-revoke share ini.');
            }

            if ($shareModel->revokeShare($shareId, $userId)) {
                $shareModel->logAudit($share['filing_id'], $userId, 'revoke_share', "Share ID $shareId revoked.");
                echo json_encode(['success' => true, 'message' => 'Share berhasil di-revoke.']);
            } else {
                throw new Exception('Gagal revoke share.');
            }
            break;

        case 'validate':
            $rawCode = $_POST['share_code'] ?? '';
            if (! $shareService->validateShareCodeFormat($rawCode)) {
                throw new Exception('Format share code tidak valid.');
            }

            $hash = $shareService->hashShareCode($rawCode);
            $share = $shareModel->findActiveShareByCodeHash($hash);

            if (! $share) {
                // We don't have filing_id safely here, log generally if possible, but skip for now
                throw new Exception('Share Code tidak ditemukan, sudah dicabut, atau tidak valid.');
            }

            $filingId = $share['filing_id'];

            // Validate File Status
            if ($share['file_status'] !== 'active' || ! empty($share['file_deleted_at'])) {
                $shareModel->logAudit($filingId, $userId, 'share_access_denied', 'File inactive for share access');
                throw new Exception('File sudah tidak tersedia.');
            }

            // Validate Expiry
            if (! empty($share['expired_at']) && strtotime($share['expired_at']) < time()) {
                $shareModel->logAudit($filingId, $userId, 'share_access_denied', 'Share code expired');
                throw new Exception('Share Code sudah kadaluarsa.');
            }

            // Validate Max Access
            if (! empty($share['max_access']) && $share['access_count'] >= $share['max_access']) {
                $shareModel->logAudit($filingId, $userId, 'share_access_denied', 'Share code max access reached');
                throw new Exception('Batas maksimal penggunaan Share Code telah tercapai.');
            }

            // Validate Password
            if ($share['requires_password']) {
                $inputPassword = $_POST['share_password'] ?? '';
                if (empty($inputPassword) || ! password_verify($inputPassword, $share['password_hash'])) {
                    $shareModel->logAudit($filingId, $userId, 'share_access_denied', 'Invalid password');
                    throw new Exception('Password yang dimasukkan salah atau kosong.');
                }
            }

            // All valid, increment access and log
            $shareModel->incrementAccessCount($share['rec_id']);
            $shareModel->logAudit($filingId, $userId, 'share_access', 'Share code accessed');

            // Fetch info to return
            $stmt = $pdo_run->prepare('SELECT display_name, zip_size, created_at FROM sys_filing WHERE rec_id = ?');
            $stmt->execute([$filingId]);
            $fileInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            // Give a temporary token in session to allow download via share endpoint
            $_SESSION['share_access_'.$hash] = true;

            echo json_encode([
                'success' => true,
                'file_info' => $fileInfo,
                'allow_download' => (bool) $share['allow_download'],
                'share_hash' => $hash, // Passed back to be used in download via share
                'filing_id' => $filingId,
            ]);
            break;

        default:
            throw new Exception('Action tidak dikenali.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
