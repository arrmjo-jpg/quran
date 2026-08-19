<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('mfa-verify', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('recovery-code', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Accepting an invitation submits a secret from an unauthenticated
        // caller, so it is guessable in exactly the way a login is. The token
        // is 256 bits and brute force is not a realistic threat against it,
        // but an unlimited endpoint is still free capacity for probing and for
        // hammering bcrypt — every attempt costs a password hash.
        RateLimiter::for('invitation-accept', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
