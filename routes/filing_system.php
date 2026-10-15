<?php

use App\Http\Controllers\CbtOps\FilingAccessRequestController;
use App\Http\Controllers\CbtOps\FilingFileTypeController;
use App\Http\Controllers\CbtOps\FilingGroupController;
use App\Http\Controllers\CbtOps\FilingLinkController;
use App\Http\Controllers\CbtOps\FilingSystemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Link publik Filing System (Fase 2) — TANPA legacy.auth
|--------------------------------------------------------------------------
| Token dibuat/dicabut dari modal Bagikan (2B). Gerbang format token ada di
| controller; pola route membatasi karakter supaya token aneh tidak masuk.
*/
Route::get('/filing-system/s/{token}', [FilingLinkController::class, 'show'])
    ->where('token', '[A-Za-z0-9_-]{1,128}')
    ->name('filing-system.link.show');

Route::post('/filing-system/s/{token}/password', [FilingLinkController::class, 'password'])
    ->where('token', '[A-Za-z0-9_-]{1,128}')
    ->name('filing-system.link.password');

Route::get('/filing-system/s/{token}/download', [FilingLinkController::class, 'download'])
    ->where('token', '[A-Za-z0-9_-]{1,128}')
    ->name('filing-system.link.download');

/*
|--------------------------------------------------------------------------
| Minta Akses (Fase 3A) — intake dari link token mode 1
|--------------------------------------------------------------------------
| Shim login menyimpan url.intended (kembali ke halaman link sesudah login);
| endpoint minta akses mengirim TOKEN (bukan id file mentah) dan memeriksa
| hak server-side. Halaman no-access hanya menampilkan nama file + pemilik.
*/
Route::get('/filing-system/s/{token}/masuk', [FilingAccessRequestController::class, 'loginIntent'])
    ->where('token', '[A-Za-z0-9_-]{1,128}')
    ->name('filing-system.link.login');

Route::post('/filing-system/s/{token}/minta-akses', [FilingAccessRequestController::class, 'storeFromLink'])
    ->where('token', '[A-Za-z0-9_-]{1,128}')
    ->name('filing-system.link.request');

Route::middleware('legacy.auth')->prefix('filing-system')->name('filing-system.')->group(function () {
    Route::get('/', [FilingSystemController::class, 'index'])->name('index');
    Route::match(['get', 'post'], '/berita-acara', [FilingSystemController::class, 'testDocument'])->name('berita-acara');
    Route::get('/share-access', [FilingSystemController::class, 'shareAccess'])->name('share-access');
    Route::get('/audit', [FilingSystemController::class, 'audit'])->name('audit');
    Route::get('/thumbnail/{id}', [FilingSystemController::class, 'thumbnail'])->name('thumbnail');
    Route::get('/admin', [FilingSystemController::class, 'adminIndex'])->name('admin');
    Route::post('/admin/action', [FilingSystemController::class, 'adminAction'])->name('admin.action');
    Route::get('/admin/file-types', [FilingFileTypeController::class, 'index'])->name('admin.file-types.index');
    Route::post('/admin/file-types', [FilingFileTypeController::class, 'store'])->name('admin.file-types.store');
    Route::post('/admin/file-types/{id}', [FilingFileTypeController::class, 'update'])->whereNumber('id')->name('admin.file-types.update');
    Route::post('/admin/file-types/{id}/toggle', [FilingFileTypeController::class, 'toggle'])->whereNumber('id')->name('admin.file-types.toggle');

    // Meja keputusan minta akses (Fase 3B): aksi tinggal di sini, notifikasi menaut ke sini.
    Route::get('/permintaan', [FilingAccessRequestController::class, 'index'])->name('requests.index');
    Route::post('/permintaan/{id}/setujui', [FilingAccessRequestController::class, 'approve'])->whereNumber('id')->name('requests.approve');
    Route::post('/permintaan/{id}/tolak', [FilingAccessRequestController::class, 'reject'])->whereNumber('id')->name('requests.reject');

    Route::prefix('groups')->name('groups.')->group(function () {
        Route::get('/', [FilingGroupController::class, 'index'])->name('index');
        Route::get('/create', [FilingGroupController::class, 'create'])->name('create');
        Route::post('/', [FilingGroupController::class, 'store'])->name('store');
        Route::get('/{group}', [FilingGroupController::class, 'show'])->whereNumber('group')->name('show');
        Route::post('/{group}/update', [FilingGroupController::class, 'update'])->whereNumber('group')->name('update');
        Route::post('/{group}/archive', [FilingGroupController::class, 'archive'])->whereNumber('group')->name('archive');
        Route::post('/{group}/leave', [FilingGroupController::class, 'leave'])->whereNumber('group')->name('leave');
        Route::get('/{group}/members', [FilingGroupController::class, 'members'])->whereNumber('group')->name('members');
        Route::post('/{group}/members', [FilingGroupController::class, 'addMember'])->whereNumber('group')->name('members.add');
        Route::post('/{group}/members/{member}/role', [FilingGroupController::class, 'updateMemberRole'])->whereNumber('group')->whereNumber('member')->name('members.role');
        Route::post('/{group}/members/{member}/remove', [FilingGroupController::class, 'removeMember'])->whereNumber('group')->whereNumber('member')->name('members.remove');
        Route::post('/{group}/files/upload', [FilingGroupController::class, 'upload'])->whereNumber('group')->name('files.upload');
        Route::post('/{group}/files/move', [FilingGroupController::class, 'moveFile'])->whereNumber('group')->name('files.move');
        Route::post('/{group}/files/remove', [FilingGroupController::class, 'removeFile'])->whereNumber('group')->name('files.remove');
        Route::post('/{group}/minta-akses', [FilingAccessRequestController::class, 'storeForGroup'])->whereNumber('group')->name('request');
    });
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
