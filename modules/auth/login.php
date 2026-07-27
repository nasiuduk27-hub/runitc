<?php
// File: login.php
session_start();
require_once 'config.php'; // Sesuaikan path config.php jika posisinya di luar folder

// Jika sudah login, langsung ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

$error_message = '';

// Proses jika tombol login ditekan (Method POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $auth = new Auth($pdo_run, $pdo, $pdo_bot);
    
    // Tangkap input (bisa berupa account_id atau email tergantung perubahan di langkah 1)
    $username = trim($_POST['username'] ?? $_POST['account_id'] ?? ''); 
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        // Coba autentikasi
        $user = $auth->authenticate($username, $password);

        if ($user) {
            // Berhasil ditemukan. Cek status aktif.
            // Catatan: Jika saat daftar status diset 1 secara default, ini akan lolos
            if ($user['status'] == 1) {
                // Set Session
                $_SESSION['user_id']     = $user['user_rec_id'];
                $_SESSION['user_rec_id'] = $user['user_rec_id'];
                $_SESSION['account_id']  = $user['account_id'];
                $_SESSION['account_nm']  = $user['account_nm'];
                $_SESSION['user_name']   = $user['account_nm'];
                $_SESSION['auth_db']     = $user['auth_db'] ?? 'run';
                
                require_once BASE_PATH . '/includes/audit_helper.php';
                logAudit($pdo_run, 'LOGIN_SUCCESS', 'sysitc_login', (int) ($user['login_rec_id'] ?? 0), [
                    'account_id' => $user['account_id'] ?? '',
                ], (int) ($user['user_rec_id'] ?? 0));

                // Lempar ke dashboard
                header("Location: " . BASE_URL . "dashboard.php");
                exit;
            } else {
                require_once BASE_PATH . '/includes/audit_helper.php';
                logAudit($pdo_run, 'LOGIN_FAILED_INACTIVE', 'sysitc_login', 0, [
                    'account_id' => $username,
                ]);
                $error_message = "Akun belum aktif. Silakan verifikasi OTP.";
            }
        } else {
            require_once BASE_PATH . '/includes/audit_helper.php';
            logAudit($pdo_run, 'LOGIN_FAILED', 'sysitc_login', 0, [
                'account_id' => $username,
            ]);
            $error_message = "Username/Email atau Password salah!";
        }
    } else {
        $error_message = "Harap isi username dan password.";
    }
}
?>

<form action="<?= BASE_URL ?>modules/auth/login.php" method="POST">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara. ?>
    <?php // echo Csrf::html(); ?>
    <?php if ($error_message): ?>
        <p style="color: red;"><?= $error_message ?></p>
    <?php endif; ?>
    
    <label>Account ID / Email:</label>
    <input type="text" name="username" required>
    <br><br>
    
    <label>Password:</label>
    <input type="password" name="password" required>
    <br><br>
    
    <button type="submit">Login</button>
</form>
