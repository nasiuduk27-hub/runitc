<?php
// File: modules/auth/forgot_password.php
session_start();
require_once '../../config.php';

if (!isset($pdo_run) && isset($pdo)) {
    $pdo_run = $pdo;
}

if (!isset($pdo)) {
    $pdo = $pdo_run;
}

$auth = new Auth($pdo_run, $pdo, $pdo_bot);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_input = trim($_POST['login_input']);

    // Minta sistem OOP untuk memproses Lupa Password
    $result = $auth->requestForgotPassword($login_input);

    if ($result['success']) {
        // Siapkan Sesi untuk verify.php
        $_SESSION['verify_account_id'] = $result['account_id'];
        $_SESSION['verify_purpose']    = 'forgot_password';
        $_SESSION['verify_timeout']    = time() + 300;

        // Kirim Email
        $msg_subject = "Reset ITCONE Password";
        $msg_body = "Hi " . ucwords(strtolower($result['name'])) . ",\n\n";
        $msg_body .= "Berikut adalah 5 digit kode OTP untuk memulihkan password Anda:\n\n";
        $msg_body .= "OTP CODE = " . $result['token'] . "\n\n";
        $msg_body .= "Kode ini hanya berlaku selama 5 menit. Jangan berikan kode ini kepada siapapun.\n\nBest Regards,\nITCONE";

        Helper::kirimEmail($result['email'], $result['name'], $msg_subject, $msg_body);
        $auth->insertBotNotification($result['smart_token'], $msg_subject, $msg_body);

        $email_sensor = substr($result['email'], 0, 3) . '****' . strstr($result['email'], '@');
        Helper::pesanLayar("Kode OTP pemulihan telah dikirim ke email <b>$email_sensor</b>.<br>Hanya berlaku 5 menit!", "success", "verify.php");
    } else {
        Helper::pesanLayar($result['message'], "error", "back");
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <title>Forgot Password - IT Portal</title>
    <?php include '../../includes/head.php'; ?>
</head>

<body class="bg-gray-50 text-gray-800 font-sans h-screen overflow-hidden flex">

    <div class="hidden lg:flex lg:w-2/3 bg-indigo-900 relative items-center justify-center overflow-hidden">
        <div class="absolute top-8 left-8 z-20 bg-white p-2 rounded shadow-md flex items-center justify-center">
            <img src="<?= BASE_URL ?>assets/images/RUNITC_LOGO.png" alt="Logo IT PORTAL" class="h-8 w-auto">
        </div>
        <img src="https://images.unsplash.com/photo-1555949963-ff9fe0c870eb?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80"
            alt="Security" class="absolute inset-0 w-full h-full object-cover opacity-20 mix-blend-overlay">
        <div class="relative z-10 text-center px-12">
            <div class="bg-white/10 p-8 rounded-full inline-block mb-8 backdrop-blur-sm border border-white/20 shadow-lg">
                <i class="fas fa-shield-halved text-7xl text-indigo-300"></i>
            </div>
            <h2 class="text-4xl font-bold text-white mb-4 tracking-wide">Secure Password Recovery</h2>
            <p class="text-indigo-200 text-lg max-w-lg mx-auto">Masukkan kredensial Anda untuk menerima token akses pemulihan yang aman.</p>
        </div>
    </div>

    <div class="w-full lg:w-1/3 flex items-center justify-center bg-white p-8 overflow-y-auto">
        <div class="w-full max-w-md">
            <a href="<?= BASE_URL ?>index.php" class="inline-flex items-center text-sm text-gray-500 hover:text-indigo-600 mb-8 transition-colors">
                <i class="fas fa-chevron-left mr-2"></i> Kembali ke Login
            </a>

            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Forgot Password? ðŸ”’</h2>
                <p class="text-gray-500 text-sm">Masukkan Account ID atau Email yang terdaftar untuk menerima kode pemulihan (OTP).</p>
            </div>

            <form method="POST" action="<?= BASE_URL ?>modules/auth/forgot_password.php">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara. ?>
    <?php // echo Csrf::html(); ?>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-medium mb-1">Account ID / Email <span class="text-red-500">*</span></label>
                    <input type="text" name="login_input" required autofocus placeholder="Masukkan ID atau Email..." class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition-colors bg-gray-50 focus:bg-white">
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl shadow-md transition-all flex items-center justify-center">
                    Kirim Kode OTP <i class="fas fa-paper-plane ml-2"></i>
                </button>
            </form>
        </div>
    </div>
</body>

</html>
