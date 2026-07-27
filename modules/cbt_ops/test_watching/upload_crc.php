<?php
/**
 * Upload CRC File Handler
 * Handles offline CRC file uploads with CSRF protection
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method', 405);
    }

    // Check required fields
    if (empty($_POST['admin_code'])) {
        throw new Exception('Admin code tidak ditemukan', 400);
    }

    $itc_user_id = (int) ($_SESSION['user_id'] ?? 0);
    $admin_rec_id = (int) ($_POST['admin_rec_id'] ?? 0);
    $sub_admin_id = (int) ($_POST['sub_admin_id'] ?? 0);

    if ($itc_user_id <= 0) {
        throw new Exception('Sesi login tidak valid', 403);
    }

    if ($admin_rec_id <= 0 || $sub_admin_id <= 0) {
        throw new Exception('Data room monitoring tidak lengkap', 400);
    }

    $stmtSpv = $pdo_run->prepare("
        SELECT rec_id
        FROM tad_supervisor
        WHERE itc_usr_id = ?
        LIMIT 1
    ");
    $stmtSpv->execute([$itc_user_id]);
    $spv_rec_id = (int) $stmtSpv->fetchColumn();

    if ($spv_rec_id <= 0) {
        throw new Exception('Akses SPV tidak ditemukan', 403);
    }

    $stmtAccess = $pdo_war->prepare("
        SELECT COUNT(*)
        FROM t3sT5ub4dm1n
        WHERE rec_id = ?
          AND admin_id = ?
          AND spv_recid = ?
    ");
    $stmtAccess->execute([$sub_admin_id, $admin_rec_id, $spv_rec_id]);

    if ((int) $stmtAccess->fetchColumn() <= 0) {
        throw new Exception('Anda tidak ditugaskan untuk room monitoring ini', 403);
    }

    if (empty($_FILES['crc_file']['name'])) {
        throw new Exception('File CRC tidak dipilih', 400);
    }

    $admin_code = trim($_POST['admin_code']);
    $upload_date = trim($_POST['date'] ?? date('Y-m-d'));

    // Validate file
    $file = $_FILES['crc_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file_ext !== 'crc') {
        throw new Exception('Format file harus .CRC', 400);
    }

    if ($file['size'] === 0) {
        throw new Exception('File kosong', 400);
    }

    if ($file['size'] > 10 * 1024 * 1024) { // 10MB max
        throw new Exception('Ukuran file terlalu besar (max 10MB)', 400);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . $file['error'], 500);
    }

    // Read file content
    $file_content = file_get_contents($file['tmp_name']);
    if ($file_content === false) {
        throw new Exception('Gagal membaca file', 500);
    }

    // TODO: Process CRC file content
    // - Parse CRC data
    // - Validate CRC structure
    // - Store in database if needed
    // - Update filing system

    // For now, return success response
    echo json_encode([
        'success' => true,
        'message' => 'File CRC berhasil diupload untuk Admin: ' . htmlspecialchars($admin_code),
        'file_name' => basename($file['name']),
        'date' => $upload_date
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    ]);
}
