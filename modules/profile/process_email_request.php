<?php
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$user_recid = $_SESSION['user_id'] ?? 0;
if (!$user_recid) {
    $_SESSION['error_msg'] = 'Sesi login tidak ditemukan. Silakan login kembali.';
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$new_email = strtolower(trim($_POST['new_email'] ?? ''));

try {
    if ($new_email === '' || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Format email baru tidak valid.');
    }

    $stmt_user = $pdo_run->prepare("SELECT usr.account_nm, log.account_id, log.email_id FROM sysitc_users usr JOIN sysitc_login log ON usr.login_rec_id = log.rec_id WHERE usr.rec_id = ? LIMIT 1");
    $stmt_user->execute([$user_recid]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Data user tidak ditemukan.');
    }

    $current_email = strtolower(trim($user['email_id'] ?? ''));
    if ($current_email === $new_email) {
        throw new Exception('Email baru sama dengan email saat ini.');
    }

    $stmt_login_exists = $pdo_run->prepare("SELECT rec_id FROM sysitc_login WHERE LOWER(email_id) = ? LIMIT 1");
    $stmt_login_exists->execute([$new_email]);
    if ($stmt_login_exists->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Email baru sudah digunakan oleh akun lain.');
    }

    $stmt_mail_exists = $pdo_run->prepare("SELECT rec_id FROM sysitc_usermail WHERE LOWER(email) = ? AND user_recid <> ? LIMIT 1");
    $stmt_mail_exists->execute([$new_email, $user_recid]);
    if ($stmt_mail_exists->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Email baru sudah terdaftar di akun lain.');
    }

    $otp = (string) random_int(10000, 99999);
    $_SESSION['change_email_new'] = $new_email;
    $_SESSION['change_email_old'] = $current_email;
    $_SESSION['change_email_otp'] = $otp;
    $_SESSION['change_email_exp'] = time() + 300;

    $subject = 'Verifikasi Perubahan Email - ITCONE';
    $body  = "Halo " . ucwords(strtolower($user['account_nm'] ?? 'User')) . ",\n\n";
    $body .= "Kami menerima permintaan perubahan email akun ITCONE Anda.\n";
    $body .= "Berikut adalah 5 digit kode OTP Anda:\n\n";
    $body .= "OTP CODE = " . $otp . "\n\n";
    $body .= "Kode ini berlaku selama 5 menit. Jika Anda tidak meminta perubahan email, abaikan email ini.\n\n";
    $body .= "Salam,\nTim ITCONE";

    if (!Helper::kirimEmail($new_email, $user['account_nm'] ?? 'User', $subject, $body)) {
        throw new Exception('Gagal mengirim OTP ke email baru. Silakan coba lagi.');
    }

    $_SESSION['success_msg'] = 'OTP perubahan email telah dikirim ke email baru.';
    header('Location: verify_email_change');
    exit();
} catch (Exception $e) {
    unset($_SESSION['change_email_new'], $_SESSION['change_email_old'], $_SESSION['change_email_otp'], $_SESSION['change_email_exp']);
    $_SESSION['error_msg'] = $e->getMessage();
    header('Location: index.php');
    exit();
}
