<?php

require_once __DIR__.'/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$user_recid = $_SESSION['user_id'] ?? 0;
if (! $user_recid) {
    $_SESSION['error_msg'] = 'Sesi login tidak ditemukan. Silakan login kembali.';
    header('Location: '.BASE_URL.'index.php');
    exit();
}

$input_otp = trim($_POST['full_token'] ?? '');
$new_email = $_SESSION['change_email_new'] ?? '';
$old_email = $_SESSION['change_email_old'] ?? '';
$session_otp = $_SESSION['change_email_otp'] ?? '';
$expires_at = $_SESSION['change_email_exp'] ?? 0;

try {
    if ($new_email === '' || $session_otp === '' || ! $expires_at) {
        throw new Exception('Sesi perubahan email tidak ditemukan. Silakan ulangi proses.');
    }

    if (time() > (int) $expires_at) {
        throw new Exception('Kode OTP sudah kedaluwarsa. Silakan minta kode baru.');
    }

    if ($input_otp === '' || $input_otp !== $session_otp) {
        throw new Exception('Kode OTP yang Anda masukkan salah.');
    }

    $stmt_user = $pdo_run->prepare('SELECT usr.login_rec_id, usr.account_nm FROM sysitc_users usr WHERE usr.rec_id = ? LIMIT 1');
    $stmt_user->execute([$user_recid]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (! $user) {
        throw new Exception('Data user tidak ditemukan.');
    }

    $pdo_run->beginTransaction();

    $stmt_login = $pdo_run->prepare('UPDATE sysitc_login SET email_id = ? WHERE rec_id = ?');
    $stmt_login->execute([$new_email, $user['login_rec_id']]);

    $stmt_mail = $pdo_run->prepare('SELECT rec_id FROM sysitc_usermail WHERE user_recid = ? AND asdefault = 1 LIMIT 1');
    $stmt_mail->execute([$user_recid]);
    $mail = $stmt_mail->fetch(PDO::FETCH_ASSOC);

    if ($mail) {
        $stmt_update_mail = $pdo_run->prepare('UPDATE sysitc_usermail SET email = ? WHERE rec_id = ?');
        $stmt_update_mail->execute([$new_email, $mail['rec_id']]);
    } else {
        $stmt_insert_mail = $pdo_run->prepare('INSERT INTO sysitc_usermail (user_recid, email, asdefault) VALUES (?, ?, 1)');
        $stmt_insert_mail->execute([$user_recid, $new_email]);
    }

    $pdo_run->commit();

    if ($old_email !== '' && filter_var($old_email, FILTER_VALIDATE_EMAIL)) {
        $subject = 'Notifikasi Perubahan Email - ITCONE';
        $body = 'Halo '.ucwords(strtolower($user['account_nm'] ?? 'User')).",\n\n";
        $body .= "Email akun ITCONE Anda telah diganti menjadi {$new_email}.\n";
        $body .= "Jika perubahan ini bukan dilakukan oleh Anda, segera hubungi Administrator.\n\n";
        $body .= "Salam,\nTim ITCONE";
        Helper::kirimEmail($old_email, $user['account_nm'] ?? 'User', $subject, $body);
    }

    unset($_SESSION['change_email_new'], $_SESSION['change_email_old'], $_SESSION['change_email_otp'], $_SESSION['change_email_exp']);
    $_SESSION['success_msg'] = 'Email berhasil diganti.';
    header('Location: index.php');
    exit();
} catch (Exception $e) {
    if ($pdo_run->inTransaction()) {
        $pdo_run->rollBack();
    }

    $_SESSION['error_msg'] = $e->getMessage();
    header('Location: verify_email_change');
    exit();
}
