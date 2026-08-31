<?php

require_once __DIR__.'/../../config.php';

if (! isset($_SESSION['user_id'])) {
    header('Location: '.rtrim(BASE_URL, '/').'/index.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$redirect = (string) ($_GET['redirect'] ?? '');
$fallbackUrl = rtrim(BASE_URL, '/').'/modules/notifications/index.php';

try {
    (new Notification($pdo_run))->markAllAsRead($userId);
} catch (Throwable $e) {
    error_log('Notification mark all read failed: '.$e->getMessage());
}

if ($redirect !== '' && strpos($redirect, rtrim(BASE_URL, '/')) === 0) {
    header('Location: '.$redirect);
    exit;
}

header('Location: '.$fallbackUrl);
exit;
