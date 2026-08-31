<?php

require_once __DIR__.'/../../config.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    require_once BASE_PATH.'/includes/audit_helper.php';
    logAudit($pdo_run, 'LOGOUT', 'sysitc_users', $userId);
}

Session::destroy();

header('Location: '.BASE_URL.'index.php');
exit;
