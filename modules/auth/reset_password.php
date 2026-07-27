<?php
// File: modules/auth/reset_password.php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['reset_account_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$auth = new Auth($pdo_run, $pdo, $pdo_bot);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password_id'];
    $retype   = $_POST['retype_password'];

    // Minta sistem OOP untuk mereset password
    $result = $auth->resetPassword($_SESSION['reset_account_id'], $password, $retype);

    if ($result['success']) {
        unset($_SESSION['reset_account_id']);
        Helper::pesanLayar($result['message'] . '<br>Silakan login dengan password baru Anda.', 'success', BASE_URL . 'index.php');
    } else {
        Helper::pesanLayar($result['message'], 'error', 'back');
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Buat Password Baru - IT Portal</title>
    <?php include __DIR__ . '/../../includes/head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50 font-sans h-screen flex items-center justify-center p-4">

    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 w-full max-w-md p-10 relative">
        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-green-400 to-emerald-500"></div>

        <div class="mb-8 mt-2">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Buat Password Baru</h2>
            <p class="text-gray-500 text-sm">Silakan buat kata sandi baru untuk akun Anda. Gunakan kombinasi yang kuat.</p>
        </div>

        <form method="POST" action="<?= BASE_URL ?>modules/auth/reset_password.php">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara. ?>
    <?php // echo Csrf::html(); ?>
            <div class="mb-5">
                <label class="block text-gray-700 text-sm font-medium mb-1">Password Baru <span class="text-red-500">*</span></label>
                <input type="password" name="password_id" required minlength="6" placeholder="Minimal 6 karakter..." class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 outline-none transition-colors bg-gray-50 focus:bg-white">
            </div>
            
            <div class="mb-8">
                <label class="block text-gray-700 text-sm font-medium mb-1">Ketik Ulang Password <span class="text-red-500">*</span></label>
                <input type="password" name="retype_password" required minlength="6" placeholder="Ulangi password di atas..." class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 outline-none transition-colors bg-gray-50 focus:bg-white">
            </div>

            <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-3.5 rounded-xl shadow-md transition-all flex items-center justify-center">
                <i class="fas fa-save mr-2"></i> Simpan Password Baru
            </button>
        </form>
    </div>
</body>
</html>
