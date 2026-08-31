<?php

namespace App\Providers;

use App\Services\LegacyLayoutService;
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
        View::composer(['layouts.app', 'layouts.filing', 'filing-system.index'], function ($view): void {
            $view->with(app(LegacyLayoutService::class)->getViewData());
        });
    }
}
