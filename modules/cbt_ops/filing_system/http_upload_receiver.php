<?php
// File: modules/cbt_ops/filing_system/http_upload_receiver.php

require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');

function sendHttpUploadResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function normalizeHttpUploadPath(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#/+#', '/', $path);
    $path = ltrim((string) $path, '/');

    $parts = [];
    foreach (explode('/', $path) as $part) {
        $part = trim($part);
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            sendHttpUploadResponse(400, ['success' => false, 'message' => 'Path tidak valid.']);
        }

        $parts[] = $part;
    }

    return implode('/', $parts);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendHttpUploadResponse(405, ['success' => false, 'message' => 'Method tidak diizinkan.']);
}

$storageRoot = trim((string) (getenv('FILING_HTTP_UPLOAD_STORAGE_ROOT') ?: ''));
$token = (string) (getenv('FILING_HTTP_UPLOAD_TOKEN') ?: '');

if ($storageRoot === '' || $token === '') {
    sendHttpUploadResponse(503, ['success' => false, 'message' => 'HTTP upload receiver belum dikonfigurasi.']);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) || !hash_equals($token, trim($matches[1]))) {
    sendHttpUploadResponse(401, ['success' => false, 'message' => 'Token tidak valid.']);
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    sendHttpUploadResponse(400, ['success' => false, 'message' => 'File belum dikirim.']);
}

$upload = $_FILES['file'];
if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    sendHttpUploadResponse(400, ['success' => false, 'message' => 'Upload file gagal. Error: ' . ($upload['error'] ?? 'unknown')]);
}

$tmpName = (string) ($upload['tmp_name'] ?? '');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    sendHttpUploadResponse(400, ['success' => false, 'message' => 'File upload tidak valid.']);
}

$storagePath = normalizeHttpUploadPath((string) ($_POST['storage_path'] ?? ''));
if ($storagePath === '' || strtolower(pathinfo($storagePath, PATHINFO_EXTENSION)) !== 'zip') {
    sendHttpUploadResponse(400, ['success' => false, 'message' => 'Storage path harus file ZIP.']);
}

$storageRoot = rtrim(str_replace('\\', '/', $storageRoot), '/');
$targetPath = $storageRoot . '/' . $storagePath;
$targetDir = dirname($targetPath);

if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    sendHttpUploadResponse(500, ['success' => false, 'message' => 'Gagal membuat folder tujuan.']);
}

if (!is_writable($targetDir)) {
    sendHttpUploadResponse(500, ['success' => false, 'message' => 'Folder tujuan tidak writable.']);
}

if (!@move_uploaded_file($tmpName, $targetPath)) {
    sendHttpUploadResponse(500, ['success' => false, 'message' => 'Gagal menyimpan file.']);
}

$localSize = filesize($targetPath);
$expectedSize = isset($_POST['file_size']) && is_numeric($_POST['file_size']) ? (int) $_POST['file_size'] : 0;
if ($expectedSize > 0 && $localSize !== $expectedSize) {
    @unlink($targetPath);
    sendHttpUploadResponse(500, ['success' => false, 'message' => 'Ukuran file tidak sesuai.']);
}

sendHttpUploadResponse(200, [
    'success' => true,
    'message' => 'File berhasil diterima.',
    'storage_path' => $storagePath,
    'size' => $localSize,
]);
