<?php

namespace FluxIcons;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class FluxIconsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge the package configuration with the application's copy.
        $this->mergeConfigFrom(__DIR__.'/../config/flux-icons.php', 'flux-icons');
    }

    public function boot(): void
    {
        // Publish the configuration file
        $this->publishes([
            __DIR__.'/../config/flux-icons.php' => config_path('flux-icons.php'),
        ], 'flux-icons-config');

        // Register the commands
        $this->bootCommands();
    }
    
    public function bootCommands()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Console\BuildFluxIconsCommand::class,
        ]);
    }
}