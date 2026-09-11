<?php

namespace App\Providers;

use App\Services\Ssw\SswCallbackService;
use App\Services\Ssw\SswClient;
use App\Services\Ssw\SswMppdService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Integrasi SSW — satu instance per request supaya token yang sudah
        // diambil tidak dicari ulang di dalam satu siklus.
        $this->app->singleton(SswClient::class, fn () => new SswClient(config('ssw')));

        $this->app->singleton(
            SswMppdService::class,
            fn ($app) => new SswMppdService($app->make(SswClient::class), config('ssw'))
        );

        $this->app->singleton(SswCallbackService::class, fn () => new SswCallbackService(config('ssw')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();

        Carbon::setLocale('id');

        if (config('app.env') == 'production') {
            URL::forceScheme('https');
        }
    }
}
