<?php

require_once __DIR__.'/../../config.php';

if (! isset($_SESSION['user_id'])) {
    header('Location: '.rtrim(BASE_URL, '/').'/index.php');
    exit;
}

$notificationId = (int) ($_GET['id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$fallbackUrl = rtrim(BASE_URL, '/').'/dashboard.php';

try {
    $targetUrl = (new Notification($pdo_run))->markAsRead($notificationId, $userId);
} catch (Throwable $e) {
    error_log('Notification read failed: '.$e->getMessage());
    $targetUrl = null;
}

header('Location: '.($targetUrl ?: $fallbackUrl));
exit;
