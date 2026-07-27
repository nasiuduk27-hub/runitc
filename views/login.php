<?php
// Variabel yang digunakan: $carousels, $error
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>RUN-ITC | Login</title>
    <?php include __DIR__ . '/../includes/head.php'; ?>
</head>
<body class="bg-gray-50 text-gray-800 font-sans h-screen overflow-hidden flex">
    <div class="hidden lg:flex lg:w-2/3 bg-gray-200 relative items-center justify-center overflow-hidden">
        <div class="w-full h-full relative" id="slider">
            <?php if (count($carousels) > 0): ?>
                <?php foreach ($carousels as $index => $item): ?>
                    <div class="slide absolute inset-0 transition-opacity duration-1000 <?= $index === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0' ?>"
                        data-duration="<?= htmlspecialchars((string)($item['duration'] ?? 5000)) ?>">
                        <?php if (!empty($item['url'])): ?>
                            <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank" class="absolute inset-0 z-30 cursor-pointer block"></a>
                        <?php endif; ?>
                        <img src="<?= BASE_URL ?>assets/images/carousell/<?= htmlspecialchars($item['file_name']) ?>"
                            alt="<?= htmlspecialchars($item['title']) ?>"
                            class="w-full h-full object-cover relative z-0">
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-12 text-white z-20 pointer-events-none">
                            <span class="bg-blue-600 text-xs font-bold px-2 py-1 rounded uppercase mb-2 inline-block shadow-md">
                                <?= htmlspecialchars($item['tag'] ?? '') ?>
                            </span>
                            <h2 class="text-3xl font-bold mb-2 drop-shadow-md"><?= htmlspecialchars($item['title'] ?? '') ?></h2>
                            <p class="text-gray-200 text-sm drop-shadow-md"><?= htmlspecialchars($item['caption'] ?? '') ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="slide absolute inset-0 transition-opacity duration-1000 opacity-100 z-10" data-duration="5000">
                    <div class="w-full h-full bg-gray-800 flex items-center justify-center flex-col text-white p-12 text-center">
                        <h2 class="text-3xl font-bold mb-2">Welcome to RUN-ITC</h2>
                        <p class="text-gray-400">Integrated System</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="w-full lg:w-1/3 flex items-center justify-center bg-white p-8">
        <div class="w-full max-w-md">
            <div class="mb-8 flex justify-center">
                <img src="<?= BASE_URL ?>assets/images/RUNITC_LOGO.png" alt="Logo" class="h-10 w-auto">
            </div>
            <div class="text-center">
                <h2 class="text-2xl font-semibold text-gray-800 mb-2">Welcome to RUN-ITC!</h2>
                <p class="text-gray-500 text-sm mb-6">Please sign in to your account</p>
            </div>
            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-3 mb-6 text-sm rounded text-left">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
    <?php // DEBUG ONLY: CSRF token dimatikan sementara. ?>
    <?php // echo Csrf::html(); ?>
                <div class="mb-5">
                    <label class="block text-gray-700 text-sm font-medium mb-1 text-left">Account ID</label>
                    <input type="text" name="account_id" placeholder="Enter your Account ID" required autofocus
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        value="<?= isset($_POST['account_id']) ? htmlspecialchars($_POST['account_id']) : '' ?>">
                </div>
                <div class="mb-6 relative">
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-gray-700 text-sm font-medium">Password</label>
                        <a href="<?= BASE_URL ?>modules/auth/forgot_password.php" class="text-xs text-indigo-600 hover:text-indigo-800">Forgot Password?</a>
                    </div>
                    <div class="relative">
                        <input type="password" name="passwd" id="passwd" placeholder="************" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-10">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-400" onclick="togglePassword()">
                            <i class="fas fa-eye-slash" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-md shadow-sm transition-colors duration-200">
                    Sign in
                </button>
            </form>
            <p class="text-center text-sm text-gray-600 mt-5">
                New on our platform? <a href="<?= BASE_URL ?>modules/auth/register.php" class="text-indigo-600 hover:text-indigo-800 font-medium">Register an account</a> </p>
        </div>
    </div>
    <script src="<?= BASE_URL ?>assets/js/main.js"></script>
    <script>
        // Logika Slider bawaan Anda
        let slides = document.querySelectorAll('.slide');
        let currentSlide = 0;
        function nextSlide() {
            if (slides.length <= 1) return;
            slides[currentSlide].classList.replace('opacity-100', 'opacity-0');
            slides[currentSlide].classList.replace('z-10', 'z-0');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.replace('opacity-0', 'opacity-100');
            slides[currentSlide].classList.replace('z-0', 'z-10');
            let duration = slides[currentSlide].getAttribute('data-duration') || 5000;
            setTimeout(nextSlide, parseInt(duration));
        }
        if (slides.length > 1) setTimeout(nextSlide, 5000);
    </script>
</body>
</html>

