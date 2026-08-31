<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LoginController::class, 'showLoginForm'])->name('login');
Route::get('/login', [LoginController::class, 'showLoginForm']);
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::get('/modules/auth/login.php', [LoginController::class, 'showLoginForm']);
Route::post('/modules/auth/login.php', [LoginController::class, 'login']);

Route::get('/forgot-password', [LoginController::class, 'showForgotPassword'])->name('forgot-password');
Route::post('/forgot-password', [LoginController::class, 'submitForgotPassword'])->name('forgot-password.submit');
Route::get('/modules/auth/forgot_password.php', [LoginController::class, 'showForgotPassword']);
Route::post('/modules/auth/forgot_password.php', [LoginController::class, 'submitForgotPassword']);

Route::get('/register', [LoginController::class, 'showRegister'])->name('register');
Route::post('/register', [LoginController::class, 'submitRegister'])->name('register.submit');
Route::get('/register/client-search', [LoginController::class, 'clientSearch'])->name('register.client-search');
Route::get('/modules/auth/register.php', [LoginController::class, 'showRegister']);
Route::post('/modules/auth/process_register.php', [LoginController::class, 'submitRegister']);
Route::get('/modules/auth/process_register.php', [LoginController::class, 'clientSearch']);

Route::get('/verify-otp', [LoginController::class, 'showVerify'])->name('verify-otp');
Route::post('/verify-otp', [LoginController::class, 'submitVerify'])->name('verify-otp.submit');
Route::get('/modules/auth/verify.php', [LoginController::class, 'showVerify']);
Route::post('/modules/auth/verify.php', [LoginController::class, 'submitVerify']);

Route::get('/reset-password', [LoginController::class, 'showResetPassword'])->name('reset-password');
Route::post('/reset-password', [LoginController::class, 'submitResetPassword'])->name('reset-password.submit');
Route::get('/modules/auth/reset_password.php', [LoginController::class, 'showResetPassword']);
Route::post('/modules/auth/reset_password.php', [LoginController::class, 'submitResetPassword']);

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
