<?php

namespace Appstract\ArtisanScan;

use Illuminate\Support\ServiceProvider;

class ArtisanScanServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            
            $this->publishes([
                __DIR__.'/../config/scanner.php' => config_path('scanner.php'),
            ], 'config');

            $this->commands([
                Commands\Performance::class,
                Commands\Launch::class,
            ]);
        }
    }
    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/scanner.php', 'scanner');
    }
}