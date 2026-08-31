<?php

use App\Http\Middleware\CooperativeAdminAccess;
use App\Http\Middleware\LegacyAuthenticate;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('filing:process-expired --limit=100')->dailyAt('03:00')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'legacy.auth' => LegacyAuthenticate::class,
            'coop.admin' => CooperativeAdminAccess::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'modules/cbt_ops/filing_system/crc_b2_receiver',
            'modules/cbt_ops/filing_system/crc_b2_receiver.php',
            'modules/cbt_ops/filing_system/http_upload_receiver',
            'modules/cbt_ops/filing_system/http_upload_receiver.php',
            'modules/cbt_ops/test_watching/crc_receiver',
            'modules/cbt_ops/test_watching/crc_receiver.php',
            'modules/cbt_ops/test_watching/outbound_receiver',
            'modules/cbt_ops/test_watching/outbound_receiver.php',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
