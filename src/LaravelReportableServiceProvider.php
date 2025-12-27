<?php

namespace Intrfce\LaravelReportable;

use Illuminate\Support\ServiceProvider;

class LaravelReportableServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/laravel-reportable.php' => config_path('laravel-reportable.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/laravel-reportable.php', 'laravel-reportable');
    }
}
