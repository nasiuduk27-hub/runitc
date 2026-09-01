<?php

namespace App\Providers;

use App\Auth\LegacyUserProvider;
use App\Services\LayoutService;
use Illuminate\Support\Facades\Auth;
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
    }
}
