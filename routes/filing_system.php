<?php

use App\Http\Controllers\CbtOps\FilingSystemController;
use Illuminate\Support\Facades\Route;

Route::middleware('legacy.auth')->prefix('filing-system')->name('filing-system.')->group(function () {
    Route::get('/', [FilingSystemController::class, 'index'])->name('index');
    Route::match(['get', 'post'], '/berita-acara', [FilingSystemController::class, 'testDocument'])->name('berita-acara');
});

Route::middleware('legacy.auth')->group(function () {
    Route::get('/modules/cbt_ops/filing_system/index.php', [FilingSystemController::class, 'index']);
    Route::get('/modules/cbt_ops/filing_system/main.php', [FilingSystemController::class, 'index']);
    Route::get('/modules/cbt_ops/filing_system/download.php', [FilingSystemController::class, 'download']);
    Route::get('/modules/cbt_ops/filing_system/preview.php', [FilingSystemController::class, 'preview']);
    Route::get('/modules/cbt_ops/filing_system/share_access.php', [FilingSystemController::class, 'shareAccess']);
    Route::get('/modules/cbt_ops/filing_system/audit.php', [FilingSystemController::class, 'audit']);
    Route::get('/modules/cbt_ops/filing_system/info.php', [FilingSystemController::class, 'info']);
    Route::match(['get', 'post'], '/modules/cbt_ops/filing_system/share.php', [FilingSystemController::class, 'share']);
    Route::match(['get', 'post'], '/modules/cbt_ops/filing_system/permission.php', [FilingSystemController::class, 'permission']);
    Route::match(['get', 'post'], '/modules/cbt_ops/filing_system/inspect.php', [FilingSystemController::class, 'inspect']);
    Route::get('/modules/cbt_ops/filing_system/admin.php', [FilingSystemController::class, 'adminIndex']);
    Route::post('/modules/cbt_ops/filing_system/admin_action.php', [FilingSystemController::class, 'adminAction']);
});

Route::match(['get', 'post'], '/modules/cbt_ops/filing_system/upload.php', [FilingSystemController::class, 'upload']);
Route::post('/modules/cbt_ops/filing_system/action.php', [FilingSystemController::class, 'action']);
Route::match(['get', 'post'], '/modules/cbt_ops/filing_system/berita_acara.php', [FilingSystemController::class, 'testDocument']);
Route::match(['get', 'post'], '/modules/cbt_ops/filing_system/berita_acara', [FilingSystemController::class, 'testDocument']);

Route::post('/modules/cbt_ops/filing_system/http_upload_receiver.php', [FilingSystemController::class, 'httpUploadReceiver']);
Route::post('/modules/cbt_ops/filing_system/http_upload_receiver', [FilingSystemController::class, 'httpUploadReceiver']);
Route::match(['get', 'post'], '/modules/cbt_ops/filing_system/crc_b2_receiver.php', [FilingSystemController::class, 'crcB2Receiver']);
Route::match(['get', 'post'], '/modules/cbt_ops/filing_system/crc_b2_receiver', [FilingSystemController::class, 'crcB2Receiver']);
