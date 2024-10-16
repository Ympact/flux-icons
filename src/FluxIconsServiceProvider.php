<?php

namespace Ympact\FluxIcons;

use Illuminate\Support\ServiceProvider;

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

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/flux/icon/flux-icons'),
        ], 'flux-icons-icons');

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