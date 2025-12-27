<?php

namespace Intrfce\LaravelReportable;

use Illuminate\Support\ServiceProvider;
use Intrfce\LaravelReportable\Console\Commands\MakeReportableCommand;

class LaravelReportableServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/laravel-reportable.php' => config_path('laravel-reportable.php'),
            ], 'laravel-reportable-config');

            $this->publishes([
                __DIR__ . '/stubs' => base_path('stubs'),
            ], 'laravel-reportable-stubs');

            $this->publishesMigrations([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'laravel-reportable-migrations');

            $this->commands([
                MakeReportableCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/laravel-reportable.php', 'laravel-reportable');
    }
}
