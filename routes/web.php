<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Profile\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/cbt_ops.php';
require __DIR__.'/filing_system.php';
require __DIR__.'/cooperative.php';

Route::middleware('legacy.auth')->get('/dashboard', DashboardController::class)->name('dashboard');

Route::get('/dashboard.php', fn () => redirect()->route('dashboard'));
Route::get('/index.php', fn () => redirect()->route('login'));

Route::any('/modules/auth/logout.php', function (Request $request) {
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    // Hapus juga sesi native PHP (PHPSESSID) yang dipakai login lama.
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();

    return redirect()->route('login');
});

Route::middleware('legacy.auth')->group(function () {
    Route::get('/modules/profile/index.php', [ProfileController::class, 'index'])->name('profile.index');
    Route::match(['get', 'post'], '/modules/profile/process_profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::match(['get', 'post'], '/modules/profile/process_profile.php', [ProfileController::class, 'update']);
    Route::match(['get', 'post'], '/modules/profile/process_password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::match(['get', 'post'], '/modules/profile/process_password.php', [ProfileController::class, 'updatePassword']);
    Route::match(['get', 'post'], '/modules/profile/process_email_request', [ProfileController::class, 'requestEmailChange'])->name('profile.email.request');
    Route::match(['get', 'post'], '/modules/profile/process_email_request.php', [ProfileController::class, 'requestEmailChange']);
    Route::get('/modules/profile/verify_email_change', [ProfileController::class, 'verifyEmailForm'])->name('profile.verify-email');
    Route::get('/modules/profile/verify_email_change.php', [ProfileController::class, 'verifyEmailForm']);
    Route::match(['get', 'post'], '/modules/profile/process_email_verify', [ProfileController::class, 'verifyEmail'])->name('profile.email.verify');
    Route::match(['get', 'post'], '/modules/profile/process_email_verify.php', [ProfileController::class, 'verifyEmail']);

    Route::get('/modules/notifications/index.php', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/modules/notifications/read.php', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/modules/notifications/mark_all_read.php', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::get('/modules/notifications/fetch.php', [NotificationController::class, 'fetch'])->name('notifications.fetch');
});
