<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('public-api', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('orders', fn (Request $r) => [Limit::perMinute(5)->by($r->ip()), Limit::perHour(20)->by(hash('sha256', $r->input('customer.phone', $r->ip())))]);
        RateLimiter::for('contacts', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
    }
}
