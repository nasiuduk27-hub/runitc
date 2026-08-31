<?php

require_once __DIR__.'/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$user_recid = $_SESSION['user_id'] ?? 0;
if (! $user_recid) {
    exit('Unauthorized access.');
}

$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($new_password) || empty($confirm_password)) {
    $_SESSION['error_msg'] = 'Semua field password harus diisi!';
    header('Location: index.php');
    exit();
}

if ($new_password !== $confirm_password) {
    $_SESSION['error_msg'] = 'Konfirmasi password tidak cocok!';
    header('Location: index.php');
    exit();
}

$userClass = new User($pdo_run);
if ($userClass->changePassword($user_recid, $new_password)) {
    $_SESSION['success_msg'] = 'Password berhasil diperbarui!';
} else {
    $_SESSION['error_msg'] = 'Gagal memperbarui password. Silakan coba lagi.';
}

header('Location: index.php');
exit();
