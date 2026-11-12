<?php

namespace App\Providers;

use App\Services\HomeQueryService;
use App\Services\OutboxDeliveryRegistry;
use App\Support\BusinessClock;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as BladeView;

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
        if ($this->app->runningInConsole()) {
            ServeCommand::$passthroughVariables = array_values(array_unique([...ServeCommand::$passthroughVariables, 'TEMP', 'TMP', 'TMPDIR']));
        }

        View::composer(['components.storefront.navigation', 'components.storefront.panels'], function (BladeView $view): void {
            /** @var HomeQueryService $home */
            $home = app(HomeQueryService::class);

            $view->with([
                'navGenderCategories' => $home->categories(),
                'navSilhouettes' => $home->homeNavSilhouettes(),
                'navActivities' => $home->homeNavActivities(),
            ]);
        });
    }
}
