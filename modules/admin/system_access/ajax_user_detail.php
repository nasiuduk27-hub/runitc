<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config.php';

$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID user tidak valid.']);
    exit;
}

try {
    $model = new SystemAccess($pdo_run);
    $detail = $model->getUserDetail($userId);

    if (!$detail) {
        http_response_code(404);
        echo json_encode(['error' => 'User tidak ditemukan.']);
        exit;
    }

    $detail['lastlogin'] = !empty($detail['lastlogin']) && $detail['lastlogin'] !== '0000-00-00 00:00:00'
        ? date('d M Y H:i', strtotime($detail['lastlogin']))
        : '-';

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($detail, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal memuat detail: ' . $e->getMessage()]);
}
