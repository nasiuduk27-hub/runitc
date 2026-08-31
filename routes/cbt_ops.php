<?php

use App\Http\Controllers\CbtOps\TestAdminController;
use App\Http\Controllers\CbtOps\TestPlanController;
use App\Http\Controllers\CbtOps\TestWatchingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('legacy.auth')->prefix('cbt-ops')->name('cbt-ops.')->group(function () {
    Route::view('/', 'cbt-ops.index')->name('index');
    Route::match(['get', 'post'], '/test-admin', [TestAdminController::class, 'index'])->name('test-admin.index');
    Route::get('/test-admin/participant-recap-print', [TestAdminController::class, 'participantRecapPrint'])->name('test-admin.participant-recap-print');
    Route::match(['get', 'post'], '/test-watching/monitoring', [TestWatchingController::class, 'monitoring'])->name('test-watching.monitoring');
    Route::match(['get', 'post'], '/test-watching/monitoring-hybrid', [TestWatchingController::class, 'monitoringHybrid'])->name('test-watching.monitoring-hybrid');
    Route::get('/test-watching/participant-photo', [TestWatchingController::class, 'participantPhoto'])->name('test-watching.participant-photo');
    Route::post('/test-watching/timer-control', [TestWatchingController::class, 'timerControl'])->name('test-watching.timer-control');
    Route::post('/test-watching/timer-control-room', [TestWatchingController::class, 'timerControlRoom'])->name('test-watching.timer-control-room');
    Route::post('/test-watching/outbound-receiver', [TestWatchingController::class, 'outboundReceiver'])->name('test-watching.outbound-receiver');
    Route::post('/test-watching/upload-crc', [TestWatchingController::class, 'uploadCrc'])->name('test-watching.upload-crc');
    Route::post('/test-watching/generate-crc', [TestWatchingController::class, 'generateCrc'])->name('test-watching.generate-crc');
    Route::get('/test-plan', [TestPlanController::class, 'index'])->name('test-plan.index');
    Route::get('/test-plan/create', [TestPlanController::class, 'create'])->name('test-plan.create');
    Route::post('/test-plan', [TestPlanController::class, 'store'])->name('test-plan.store');
    Route::get('/test-plan/{id}/edit', [TestPlanController::class, 'edit'])->name('test-plan.edit');
    Route::put('/test-plan/{id}', [TestPlanController::class, 'update'])->name('test-plan.update');
    Route::delete('/test-plan/{id}', [TestPlanController::class, 'destroy'])->name('test-plan.destroy');
});

Route::post('/modules/cbt_ops/test_watching/outbound_receiver.php', [TestWatchingController::class, 'outboundReceiver']);
Route::post('/modules/cbt_ops/test_watching/outbound_receiver', [TestWatchingController::class, 'outboundReceiver']);
Route::post('/cbt-ops/test-watching/crc-receiver', [TestWatchingController::class, 'crcReceiver'])->name('cbt-ops.test-watching.crc-receiver');
Route::post('/modules/cbt_ops/test_watching/crc_receiver.php', [TestWatchingController::class, 'crcReceiver']);
Route::post('/modules/cbt_ops/test_watching/crc_receiver', [TestWatchingController::class, 'crcReceiver']);

Route::middleware('legacy.auth')->group(function () {
    Route::match(['get', 'post'], '/modules/cbt_ops/test_watching/monitoring.php', [TestWatchingController::class, 'monitoring']);
    Route::match(['get', 'post'], '/modules/cbt_ops/test_watching/monitoring_hybrid.php', [TestWatchingController::class, 'monitoringHybrid']);
    Route::match(['get', 'post'], '/modules/cbt_ops/test_admin/index.php', [TestAdminController::class, 'index']);
    Route::get('/modules/cbt_ops/test_admin/participant_recap_print.php', [TestAdminController::class, 'participantRecapPrint']);
    Route::get('/modules/cbt_ops/test_watching/participant_photo.php', [TestWatchingController::class, 'participantPhoto']);
    Route::get('/modules/cbt_ops/test_watching/participant_photo', [TestWatchingController::class, 'participantPhoto']);
    Route::post('/modules/cbt_ops/test_watching/timer_control.php', [TestWatchingController::class, 'timerControl']);
    Route::post('/modules/cbt_ops/test_watching/timer_control', [TestWatchingController::class, 'timerControl']);
    Route::post('/modules/cbt_ops/test_watching/timer_control_room.php', [TestWatchingController::class, 'timerControlRoom']);
    Route::post('/modules/cbt_ops/test_watching/timer_control_room', [TestWatchingController::class, 'timerControlRoom']);
    Route::post('/modules/cbt_ops/test_watching/upload_crc.php', [TestWatchingController::class, 'uploadCrc']);
    Route::post('/modules/cbt_ops/test_watching/upload_crc', [TestWatchingController::class, 'uploadCrc']);
    Route::post('/modules/cbt_ops/test_watching/generate_crc.php', [TestWatchingController::class, 'generateCrc']);
    Route::post('/modules/cbt_ops/test_watching/generate_crc', [TestWatchingController::class, 'generateCrc']);

    Route::get('/modules/cbt_ops/test_plan/index.php', [TestPlanController::class, 'index']);
    Route::get('/modules/cbt_ops/test_plan/create.php', [TestPlanController::class, 'create']);
    Route::post('/modules/cbt_ops/test_plan/create.php', [TestPlanController::class, 'store']);
    Route::get('/modules/cbt_ops/test_plan/edit.php', function (Request $request, TestPlanController $controller) {
        return $controller->edit($request, (int) $request->query('id'));
    });
    Route::post('/modules/cbt_ops/test_plan/edit.php', function (Request $request, TestPlanController $controller) {
        return $controller->update($request, (int) $request->query('id'));
    });
    Route::post('/modules/cbt_ops/test_plan/delete.php', function (Request $request, TestPlanController $controller) {
        return $controller->destroy($request, (int) $request->input('id'));
    });
});
