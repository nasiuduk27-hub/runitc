<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Notifications\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/cbt_ops.php';
require __DIR__.'/filing_system.php';
require __DIR__.'/cooperative.php';
require __DIR__.'/profile.php';

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
    Route::get('/modules/notifications/index.php', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/modules/notifications/read.php', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/modules/notifications/mark_all_read.php', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::get('/modules/notifications/fetch.php', [NotificationController::class, 'fetch'])->name('notifications.fetch');
});
