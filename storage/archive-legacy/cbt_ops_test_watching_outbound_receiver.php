<?php

date_default_timezone_set('Asia/Jakarta');

/**
 * Outbound ZIP Receiver
 *
 * Deploy ke: /www/wwwroot/cbt.toeic.or.id/docs/CBT/CRC/outbound_receiver.php
 *
 * Environment variables:
 *   OUTBOUND_RECEIVER_TOKEN  - Bearer token untuk autentikasi (default: annas123)
 *   OUTBOUND_STORAGE_ROOT    - Folder OUTBOUND (default: /www/wwwroot/cbt.toeic.or.id/docs/CBT/OUTBOUND)
 */
function outJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function outSafeAdminNo(string $adminNo): string
{
    $adminNo = basename($adminNo);
    $adminNo = preg_replace('/\s+/', '_', $adminNo);
    $adminNo = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $adminNo);
    $adminNo = trim($adminNo, '._-');

    return $adminNo;
}

function outIsValidFileName(string $adminNo, string $fileName): bool
{
    $fileName = basename(str_replace('\\', '/', $fileName));
    if ($adminNo === '' || $fileName === '') {
        return false;
    }

    return preg_match('/^'.preg_quote($adminNo, '/').'-.*-DATA\.ZIP$/i', $fileName) === 1;
}

function outPublicUrl(string $fileName): string
{
    $baseUrl = rtrim((string) (getenv('OUTBOUND_PUBLIC_BASE_URL') ?: ''), '/');
    if ($baseUrl === '') {
        $baseUrl = 'https://cbt.toeic.or.id/docs/CBT/OUTBOUND';
    }

    return $baseUrl.'/'.rawurlencode($fileName);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    outJsonResponse(405, ['success' => false, 'message' => 'Method tidak diizinkan.']);
}

$token = trim((string) (getenv('OUTBOUND_RECEIVER_TOKEN') ?: 'annas123'));
if ($token === '') {
    outJsonResponse(503, ['success' => false, 'message' => 'OUTBOUND_RECEIVER_TOKEN belum dikonfigurasi.']);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (! preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) || ! hash_equals($token, trim($matches[1]))) {
    outJsonResponse(401, ['success' => false, 'message' => 'Token tidak valid.']);
}

$action = trim((string) ($_POST['action'] ?? 'list'));
$adminNo = outSafeAdminNo((string) ($_POST['admin_no'] ?? ''));

if ($adminNo === '') {
    outJsonResponse(400, ['success' => false, 'message' => 'admin_no tidak valid.']);
}

$storageRoot = rtrim((string) (getenv('OUTBOUND_STORAGE_ROOT') ?: '/www/wwwroot/cbt.toeic.or.id/docs/CBT/OUTBOUND'), '\\/');
if (! is_dir($storageRoot)) {
    outJsonResponse(500, ['success' => false, 'message' => 'Folder OUTBOUND tidak ditemukan.']);
}

if ($action === 'list') {
    $files = [];
    foreach (scandir($storageRoot) ?: [] as $fileName) {
        if (! outIsValidFileName($adminNo, $fileName)) {
            continue;
        }

        $absolutePath = $storageRoot.DIRECTORY_SEPARATOR.$fileName;
        if (! is_file($absolutePath)) {
            continue;
        }

        $files[] = [
            'file_id' => null,
            'file_name' => $fileName,
            'file_type' => 'zip',
            'file_category' => 'outbound',
            'uploaded_at' => date('Y-m-d H:i:s', filemtime($absolutePath)),
            'size' => filesize($absolutePath),
            'download_url' => outPublicUrl($fileName),
        ];
    }

    usort($files, static function (array $a, array $b): int {
        return strcasecmp($a['file_name'], $b['file_name']);
    });

    outJsonResponse(200, [
        'success' => true,
        'admin_no' => $adminNo,
        'files' => $files,
    ]);
}

if ($action === 'download') {
    $fileName = basename(str_replace('\\', '/', (string) ($_POST['file'] ?? '')));
    if (! outIsValidFileName($adminNo, $fileName)) {
        outJsonResponse(400, ['success' => false, 'message' => 'Nama file tidak valid.']);
    }

    $absolutePath = $storageRoot.DIRECTORY_SEPARATOR.$fileName;
    if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
        outJsonResponse(404, ['success' => false, 'message' => 'File outbound tidak ditemukan.']);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.$fileName.'"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: '.filesize($absolutePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    readfile($absolutePath);
    exit;
}

outJsonResponse(400, ['success' => false, 'message' => 'Action tidak dikenal.']);
