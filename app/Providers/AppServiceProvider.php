<?php

namespace App\Providers;

use App\Models\OrderStatusHistory;
use App\Services\OutboxDeliveryRegistry;
use App\Services\StoreNotificationService;
use App\Support\BusinessClock;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OutboxDeliveryRegistry::class);
        $this->app->singleton(BusinessClock::class, fn (): BusinessClock => new BusinessClock(
            config('store.business_timezone'),
            config('store.deadlines'),
        ));
    }

    /**
     * Bootstrap any application services.
     *
     * `php artisan serve` starts the PHP server with only a short list of environment variables. Without TEMP/TMP,
     * PHP on Windows falls back to C:\Windows for temporary files, cannot write there and rejects every upload
     * ("File upload error - unable to create a temporary file"), so the temp-folder variables are passed through.
     */
    public function boot(): void
    {
        OrderStatusHistory::created(static function (OrderStatusHistory $history): void {
            app(StoreNotificationService::class)->recordForStatusHistory($history);
        });

        if ($this->app->runningInConsole()) {
            ServeCommand::$passthroughVariables = array_values(array_unique([...ServeCommand::$passthroughVariables, 'TEMP', 'TMP', 'TMPDIR']));
        }
    }
}
