<?php

use App\Http\Controllers\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('legacy.auth')->prefix('profile')->name('profile.')->group(function (): void {
    Route::get('/', [ProfileController::class, 'index'])->name('index');
    Route::match(['get', 'post'], '/update', [ProfileController::class, 'update'])->name('update');
    Route::match(['get', 'post'], '/password', [ProfileController::class, 'updatePassword'])->name('password');
    Route::match(['get', 'post'], '/email/request', [ProfileController::class, 'requestEmailChange'])->name('email.request');
    Route::get('/email/verify', [ProfileController::class, 'verifyEmailForm'])->name('verify-email');
    Route::match(['get', 'post'], '/email/verify', [ProfileController::class, 'verifyEmail'])->name('email.verify');
});

Route::middleware('legacy.auth')->group(function (): void {
    Route::get('/modules/profile/index.php', [ProfileController::class, 'index']);
    Route::match(['get', 'post'], '/modules/profile/process_profile', [ProfileController::class, 'update']);
    Route::match(['get', 'post'], '/modules/profile/process_profile.php', [ProfileController::class, 'update']);
    Route::match(['get', 'post'], '/modules/profile/process_password', [ProfileController::class, 'updatePassword']);
    Route::match(['get', 'post'], '/modules/profile/process_password.php', [ProfileController::class, 'updatePassword']);
    Route::match(['get', 'post'], '/modules/profile/process_email_request', [ProfileController::class, 'requestEmailChange']);
    Route::match(['get', 'post'], '/modules/profile/process_email_request.php', [ProfileController::class, 'requestEmailChange']);
    Route::get('/modules/profile/verify_email_change', [ProfileController::class, 'verifyEmailForm']);
    Route::get('/modules/profile/verify_email_change.php', [ProfileController::class, 'verifyEmailForm']);
    Route::match(['get', 'post'], '/modules/profile/process_email_verify', [ProfileController::class, 'verifyEmail']);
    Route::match(['get', 'post'], '/modules/profile/process_email_verify.php', [ProfileController::class, 'verifyEmail']);
});
