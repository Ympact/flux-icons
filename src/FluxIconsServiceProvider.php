<?php

namespace Ympact\FluxIcons;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Flux\FluxServiceProvider as FluxServiceProvider;

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
        app(FluxServiceProvider::class)->bootFallbackBlazeDirectivesIfBlazeIsNotInstalled();
        $this->bootCommands();
    }

    /**
     * Based on livewire/flux: `FluxServiceProvider::bootFallbackBlazeDirectivesIfBlazeIsNotInstalled()`

    public function bootFallbackBlazeDirectivesIfBlazeIsNotInstalled()
    {
        Blade::directive('blaze', fn () => '');

        // `@pure` directive has been replaced with `@blaze` in Blaze v1.0, but we need to keep it here for
        // backwards compatibility as people could have published components or custom icons using it...
        Blade::directive('pure', fn () => '');

        Blade::directive('unblaze', function ($expression) {
            return ''
                . '<'.'?php $__getScope = fn($scope = []) => $scope; ?>'
                . '<'.'?php if (isset($scope)) $__scope = $scope; ?>'
                . '<'.'?php $scope = $__getScope('.$expression.'); ?>';
        });

        Blade::directive('endunblaze', function () {
            return '<'.'?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>';
        });
    }     
    */

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
