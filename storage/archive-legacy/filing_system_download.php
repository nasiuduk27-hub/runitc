<?php

// File: modules/cbt_ops/filing_system/download.php

require_once __DIR__.'/../../../config.php';
require_once __DIR__.'/controllers/FilingDriveController.php';

$filingId = (int) ($_GET['id'] ?? 0);
$shareHash = $_GET['share_hash'] ?? null;

if ($filingId <= 0) {
    exit('ID File tidak valid.');
}

$controller = new FilingDriveController($pdo_run, $ftp_config);

if ($shareHash && isset($_SESSION['share_access_'.$shareHash])) {
    // Validasi ulang share secara internal
    $stmt = $pdo_run->prepare('SELECT s.*, f.status, f.deleted_at FROM sys_filing_share s JOIN sys_filing f ON s.filing_id = f.rec_id WHERE s.share_code_hash = ? AND s.filing_id = ? AND s.is_active = 1 LIMIT 1');
    $stmt->execute([$shareHash, $filingId]);
    $share = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($share && $share['allow_download'] && $share['status'] === 'active' && empty($share['deleted_at'])) {
        // Bypass permission service just for this specific download
        // We will momentarily inject a mock user/permission state into the controller if needed,
        // but since we are handling download via share securely, we can just execute the logic.
        // However, the cleanest way to support this without modifying the controller heavily is to
        // add a specific method for share_download in the controller.
        $controller->downloadViaShare($filingId, $shareHash, (int) $_SESSION['user_id']);
        exit;
    } else {
        exit('Share Code tidak mengizinkan unduhan atau tidak valid.');
    }
}

$controller->download($filingId);
