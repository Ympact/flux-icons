<?php

namespace Ympact\FluxIcons;

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

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/flux/icon/flux-icons'),
        ], 'flux-icons-icons');

        // Register the commands
        $this->bootFallbackBlazeDirectivesIfBlazeIsNotInstalled();
        $this->bootCommands();
    }

    public function bootFallbackBlazeDirectivesIfBlazeIsNotInstalled()
    {
        Blade::directive('pure', fn () => '');
        Blade::directive('blaze', fn () => '');
    }
    
    public function bootCommands()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Console\BuildFluxIconsCommand::class,
            Console\PublishFluxIconsVendorFileCommmand::class,
            Console\UpdateFluxIconsCommand::class,
        ]);
    }
}