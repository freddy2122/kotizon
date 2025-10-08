<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

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
        // Rate limiting for auth endpoints
        RateLimiter::for('login', function (Request $request) {
            $key = 'login:' . ($request->input('email') ?? $request->ip());
            return [
                Limit::perMinute(10)->by($key),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            $key = 'register:' . $request->ip();
            return [
                Limit::perMinute(5)->by($key),
            ];
        });

        RateLimiter::for('verify', function (Request $request) {
            $key = 'verify:' . ($request->input('email') ?? $request->ip());
            return [
                Limit::perMinute(10)->by($key),
            ];
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            $key = 'forgot:' . ($request->input('email') ?? $request->ip());
            return [
                Limit::perMinute(3)->by($key),
            ];
        });
    }
}
