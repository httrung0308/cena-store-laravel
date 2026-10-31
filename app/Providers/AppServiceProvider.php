<?php

namespace App\Providers;

use App\Support\BusinessClock;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BusinessClock::class, fn (): BusinessClock => new BusinessClock(
            config('store.business_timezone'),
            config('store.deadlines'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
