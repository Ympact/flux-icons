<?php

use Ympact\FluxIcons\Vendors\Bootstrap;
use Ympact\FluxIcons\Vendors\Codicons;
use Ympact\FluxIcons\Vendors\Flags;
use Ympact\FluxIcons\Vendors\Flowbite;
use Ympact\FluxIcons\Vendors\Fluent;
use Ympact\FluxIcons\Vendors\Healthicons;
use Ympact\FluxIcons\Vendors\Lucide;
use Ympact\FluxIcons\Vendors\MaterialIcons;
use Ympact\FluxIcons\Vendors\MaterialSymbols;
use Ympact\FluxIcons\Vendors\Mdi;
use Ympact\FluxIcons\Vendors\Tabler;

/**
 * Flux Icons configuration file
 * In case you have published the configuration file, you can modify the configuration here
 * The config file is merged with the default configuration, so you only need to specify the values you want to change
 */

return [

    /**
     * Request to restart npm dev after building icons
     */
    'request_npm_dev' => true,

    /**
     * What flux-icons:build uses when the command line does not say.
     * 'vendor': the vendor key to build from when none is given, or null to be asked
     */
    'defaults' => [
        'vendor' => null,
    ],

    /**
     * Default icons to be used in the project
     * Listing icons here will make them auto buildable and updatable through flux-icons:build and flux-icons:update commands
     * null or array ['vendor' => ['icon-name', ...] ]
     */
    'icons' => null,

    /**
     * The vendors icons can be built from, by the key they are addressed by on the command line.
     * A vendor's icons are published under that key unless its class names another namespace
     * (Material Icons publishes to `material`).
     *
     * To adjust a vendor, extend its class and register the subclass here. A vendor of your
     * own extends Ympact\FluxIcons\Contracts\Vendor. `php artisan flux-icons:make-vendor`
     * generates either.
     */
    'vendors' => [
        // https://icons.getbootstrap.com/
        'bootstrap' => Bootstrap::class,
        // https://microsoft.github.io/vscode-codicons/dist/codicon.html
        'codicons' => Codicons::class,
        // https://github.com/lipis/flag-icons/
        'flags' => Flags::class,
        // https://flowbite.com/icons/
        'flowbite' => Flowbite::class,
        // https://developer.microsoft.com/en-us/fluentui#/styles/web/icons
        'fluent' => Fluent::class,
        // https://healthicons.org/
        'healthicons' => Healthicons::class,
        // https://lucide.dev/
        'lucide' => Lucide::class,
        // https://fonts.google.com/icons
        'material-icons' => MaterialIcons::class,
        // https://fonts.google.com/icons?icon.set=Material+Symbols
        'material-symbols' => MaterialSymbols::class,
        // https://pictogrammers.com/library/mdi/
        'mdi' => Mdi::class,
        // https://tabler.io/icons
        'tabler' => Tabler::class,
    ],

    /**
     * Build options per vendor key, used by flux-icons:build and flux-icons:update. A vendor's
     * options() method lists the options it takes and their values; `--options=` on the
     * build command overrides them for that build. For example:
     * 'flags' => ['ratio' => '1x1'],
     */
    'options' => [],
];
