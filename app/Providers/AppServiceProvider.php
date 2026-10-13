<?php

namespace App\Providers;

use App\Auth\LegacyUserProvider;
use App\Repositories\FilingSystem\FileGroup;
use App\Services\LayoutService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('legacy', fn (): LegacyUserProvider => new LegacyUserProvider);

        View::composer(['layouts.app', 'layouts.filing', 'filing-system.index'], function ($view): void {
            $view->with(app(LayoutService::class)->getViewData());
        });

        View::composer('filing-system.partials.sidebar', function ($view): void {
            $userId = (int) auth_user_id();
            $myGroups = ['groups' => [], 'more' => false, 'total' => 0];

            if ($userId > 0) {
                try {
                    $myGroups = (new FileGroup(DB::connection('run')->getPdo()))->listForUser($userId, 10);
                } catch (\Throwable $e) {
                    // Tabel grup belum siap: sidebar tetap tampil tanpa grup.
                }
            }

            $view->with('myGroups', $myGroups);
        });
    }
}
