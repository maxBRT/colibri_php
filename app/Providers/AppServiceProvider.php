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
        RateLimiter::for('anthropic-enrichment', function () {
            $requestsPerMinute = max(1, (int) config('ai.providers.anthropic.rpm_limit', 5));

            return Limit::perMinute($requestsPerMinute);
        });
    }
}
