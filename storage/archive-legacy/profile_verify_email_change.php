<?php
require_once __DIR__.'/../../config.php';

$user_recid = $_SESSION['user_id'] ?? 0;
if (! $user_recid) {
    header('Location: '.BASE_URL.'index.php');
    exit();
}

if (empty($_SESSION['change_email_new']) || empty($_SESSION['change_email_otp']) || empty($_SESSION['change_email_exp'])) {
    $_SESSION['error_msg'] = 'Sesi perubahan email tidak ditemukan. Silakan ulangi proses.';
    header('Location: index.php');
    exit();
}

if (time() > (int) $_SESSION['change_email_exp']) {
    unset($_SESSION['change_email_new'], $_SESSION['change_email_old'], $_SESSION['change_email_otp'], $_SESSION['change_email_exp']);
    $_SESSION['error_msg'] = 'Kode OTP sudah kedaluwarsa. Silakan ulangi proses.';
    header('Location: index.php');
    exit();
}

$new_email = $_SESSION['change_email_new'];
$sisa_waktu = (int) $_SESSION['change_email_exp'] - time();
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Verifikasi Email Baru - ITC Portal</title>
    <?php include __DIR__.'/../../includes/head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans h-screen flex items-center justify-center p-4">
    <?php if ($success_msg || $error_msg) { ?>
        <script>
            Swal.fire({
                icon: '<?= $success_msg ? 'success' : 'error' ?>',
                title: '<?= $success_msg ? 'Berhasil!' : 'Oops...' ?>',
                text: '<?= htmlspecialchars($success_msg ?: $error_msg, ENT_QUOTES) ?>',
                confirmButtonColor: '#4f46e5'
            });
        </script>
    <?php } ?>

    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 w-full max-w-md p-10 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-indigo-500 to-purple-500"></div>

        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-5 shadow-sm border border-indigo-100">
                <i class="fas fa-envelope-open-text text-3xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Verifikasi Email Baru</h2>
            <p class="text-gray-500 text-sm">Masukkan 5 digit kode OTP yang kami kirimkan ke <b><?= htmlspecialchars($new_email) ?></b>.</p>
            <div class="mt-4 inline-block bg-red-50 text-red-600 font-semibold px-4 py-1.5 rounded-full text-sm border border-red-100">
                <i class="far fa-clock mr-1"></i> Sisa Waktu: <span id="countdown">05:00</span>
            </div>
        </div>

        <form action="process_email_verify" method="POST" class="w-full">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara.?>
    <?php // echo Csrf::html();?>
            <div class="flex justify-center gap-3 mb-8" id="otp-container">
                <input type="text" maxlength="1" class="otp-input w-14 h-16 text-center text-3xl font-bold text-gray-800 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 outline-none transition-all bg-gray-50 focus:bg-white" autofocus required>
                <input type="text" maxlength="1" class="otp-input w-14 h-16 text-center text-3xl font-bold text-gray-800 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 outline-none transition-all bg-gray-50 focus:bg-white" required>
                <input type="text" maxlength="1" class="otp-input w-14 h-16 text-center text-3xl font-bold text-gray-800 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 outline-none transition-all bg-gray-50 focus:bg-white" required>
                <input type="text" maxlength="1" class="otp-input w-14 h-16 text-center text-3xl font-bold text-gray-800 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 outline-none transition-all bg-gray-50 focus:bg-white" required>
                <input type="text" maxlength="1" class="otp-input w-14 h-16 text-center text-3xl font-bold text-gray-800 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 outline-none transition-all bg-gray-50 focus:bg-white" required>
            </div>

            <input type="hidden" name="full_token" id="full_token">

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3.5 px-4 rounded-xl transition-colors shadow-md hover:shadow-lg mb-6 flex items-center justify-center">
                Verifikasi Email
            </button>
        </form>

        <div class="text-center">
            <a href="<?= BASE_URL ?>modules/profile/index.php" class="text-sm font-medium text-gray-400 hover:text-gray-700 transition-colors">Batal & Kembali ke Profil</a>
        </div>
    </div>

    <script>
        const inputs = document.querySelectorAll('.otp-input');
        const hiddenToken = document.getElementById('full_token');

        inputs.forEach((input, index) => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value !== '' && index < inputs.length - 1) inputs[index + 1].focus();
                updateHiddenToken();
            });
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value === '' && index > 0) inputs[index - 1].focus();
            });
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 5);
                const pasteArr = pastedData.split('');
                inputs.forEach((inp, i) => { inp.value = pasteArr[i] || ''; });
                if (pasteArr.length > 0) inputs[Math.min(pasteArr.length - 1, 4)].focus();
                updateHiddenToken();
            });
        });

        function updateHiddenToken() {
            let otpCode = '';
            inputs.forEach(input => { otpCode += input.value; });
            hiddenToken.value = otpCode;
        }

        let timeLeft = <?= $sisa_waktu ?>;
        const countdownEl = document.getElementById('countdown');

        const timer = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(timer);
                countdownEl.innerText = '00:00';
                Swal.fire({
                    icon: 'error',
                    title: 'Waktu Habis!',
                    text: 'Waktu verifikasi telah habis. Silakan ulangi proses.',
                    confirmButtonColor: '#4f46e5',
                    confirmButtonText: 'Kembali'
                }).then(() => { window.location.href = '<?= BASE_URL ?>modules/profile/index.php'; });
            } else {
                const m = Math.floor(timeLeft / 60);
                const s = timeLeft % 60;
                countdownEl.innerText = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                timeLeft--;
            }
        }, 1000);
    </script>
</body>
</html>
