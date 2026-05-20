<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('moonshot-enrichment', function () {
            $requestsPerMinute = max(1, (int) config('ai.providers.moonshot.rpm_limit', 18));

            return Limit::perMinute($requestsPerMinute);
        });
    }
}
