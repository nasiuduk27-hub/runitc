<?php

use App\Http\Controllers\Cooperative\CooperativeDashboardController;
use App\Http\Controllers\Cooperative\CooperativeReportController;
use App\Http\Controllers\Cooperative\CooperativeSettingsController;
use App\Http\Controllers\Cooperative\LoanApplicationController;
use App\Http\Controllers\Cooperative\LoanController;
use App\Http\Controllers\Cooperative\LoanPaymentController;
use App\Http\Controllers\Cooperative\LoanSimulationController;
use App\Http\Controllers\Cooperative\LoanSkipController;
use App\Http\Controllers\Cooperative\MemberController;
use App\Http\Controllers\Cooperative\SavingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('legacy.auth')->prefix('cooperative')->name('cooperative.')->group(function (): void {
    Route::redirect('/', '/cooperative/dashboard')->name('index');
    Route::get('/dashboard', [CooperativeDashboardController::class, 'index'])->name('dashboard');
    Route::get('/transactions', [CooperativeDashboardController::class, 'transactions'])->name('transactions.index');
    Route::get('/transactions/my', [CooperativeDashboardController::class, 'myTransactions'])->name('transactions.my');
    Route::get('/deposits/{period}', [CooperativeDashboardController::class, 'depositDetail'])
        ->where('period', '\\d{6}')
        ->name('deposits.detail');
    Route::get('/savings/detail', [CooperativeDashboardController::class, 'savingsDetail'])->name('savings.detail');
    Route::get('/loan-calculation/detail', [CooperativeDashboardController::class, 'loanCalculationDetail'])->name('loan-calculation.detail');

    Route::get('/loan-simulation', [LoanSimulationController::class, 'index'])->name('loan-simulation.index');
    Route::post('/loan-simulation/calculate', [LoanSimulationController::class, 'calculate'])->name('loan-simulation.calculate');
    Route::get('/loan-simulation/export', [LoanSimulationController::class, 'export'])->name('loan-simulation.export');

    Route::get('/savings', [SavingsController::class, 'index'])->name('savings.index');
    Route::get('/savings/history', [SavingsController::class, 'history'])->name('savings.history');
    Route::post('/savings', [SavingsController::class, 'update'])->name('savings.update');
    Route::post('/savings/withdraw', [SavingsController::class, 'withdraw'])->name('savings.withdraw');
    Route::post('/savings/withdraw/decide', [SavingsController::class, 'decideWithdrawal'])->name('savings.withdraw.decide');

    Route::middleware('coop.admin')->group(function (): void {
        Route::get('/settings', [CooperativeSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [CooperativeSettingsController::class, 'update'])->name('settings.update');

        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/detail', [MemberController::class, 'detail'])->name('members.detail');
        Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
        Route::post('/members/store', [MemberController::class, 'store'])->name('members.store');
        Route::get('/members/{member}/sync', [MemberController::class, 'sync'])->name('members.sync');
        Route::post('/members/{member}/sync', [MemberController::class, 'doSync'])->name('members.sync.store');
    });

    Route::get('/sync/verify/{ref_token}', [MemberController::class, 'syncVerify'])->name('members.sync.verify');
    Route::post('/sync/verify/{ref_token}', [MemberController::class, 'syncVerifyStore'])->name('members.sync.verify.store');

    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/detail', [LoanController::class, 'detail'])->name('loans.detail');

    Route::get('/applications', [LoanApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/create', [LoanApplicationController::class, 'create'])->name('applications.create');
    Route::post('/applications', [LoanApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications/detail', [LoanApplicationController::class, 'detail'])->name('applications.detail');
    Route::post('/applications/recalculate', [LoanApplicationController::class, 'recalculate'])->name('applications.recalculate');
    Route::post('/applications/decide', [LoanApplicationController::class, 'decide'])->name('applications.decide');
    Route::post('/applications/post', [LoanApplicationController::class, 'post'])->name('applications.post');

    Route::middleware('coop.admin')->group(function (): void {
        Route::get('/payments', [LoanPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/create', fn (): \Illuminate\Http\RedirectResponse => redirect()->route('cooperative.payments.index'))->name('payments.create');
        Route::post('/payments', [LoanPaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/detail', [LoanPaymentController::class, 'detail'])->name('payments.detail');
        Route::post('/payments/decide', [LoanPaymentController::class, 'decide'])->name('payments.decide');
    });

    Route::get('/skips', [LoanSkipController::class, 'index'])->name('skips.index');
    Route::get('/skips/create', [LoanSkipController::class, 'create'])->name('skips.create');
    Route::post('/skips', [LoanSkipController::class, 'store'])->name('skips.store');
    Route::get('/skips/detail', [LoanSkipController::class, 'detail'])->name('skips.detail');
    Route::post('/skips/decide', [LoanSkipController::class, 'decide'])->name('skips.decide');

    Route::get('/reports', [CooperativeReportController::class, 'index'])->name('reports.index');
});
