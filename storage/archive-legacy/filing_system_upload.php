<?php

// File: modules/cbt_ops/filing_system/upload.php

ob_start();

@set_time_limit(600);
@ini_set('max_execution_time', '600');
@ini_set('default_socket_timeout', '120');

require_once __DIR__.'/../../../config.php';
require_once __DIR__.'/controllers/FilingDriveController.php';
require_once __DIR__.'/models/FilingAccess.php';

session_write_close();

header('Content-Type: application/json');

function sendUploadJson(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $body = json_encode($payload);
    header('Content-Type: application/json');
    header('Content-Length: '.strlen($body));
    header('Connection: close');
    echo $body;
}

if (($_GET['action'] ?? '') === 'access_options') {
    try {
        $accessModel = new FilingAccess($pdo_run);
        sendUploadJson(['success' => true, 'data' => $accessModel->getAccessOptions()]);
    } catch (Throwable $e) {
        sendUploadJson(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendUploadJson(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (empty($_FILES) && empty($_POST) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $maxPost = ini_get('post_max_size');
    $maxUpload = ini_get('upload_max_filesize');
    sendUploadJson(['success' => false, 'message' => "Upload gagal: Ukuran file melebihi batas server (post_max_size={$maxPost}, upload_max_filesize={$maxUpload})."]);
    exit;
}

try {
    $controller = new FilingDriveController($pdo_run, $ftp_config);
    $response = $controller->handleUpload();
} catch (Throwable $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

sendUploadJson($response);
