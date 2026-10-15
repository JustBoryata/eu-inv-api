<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        // Keyed on email + IP: slows down guessing one account's password
        // without letting one noisy IP lock out a real user elsewhere.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower($request->string('email')->value()).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(3)
            ->by((string) $request->ip()));

        // Scramble serves /docs/api only in the local environment unless this
        // allows it. The docs describe a public API and hold no data, so
        // everyone may read them.
        Gate::define('viewApiDocs', fn (?User $user): bool => true);
    }
}
