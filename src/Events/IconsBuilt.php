<?php

namespace Ympact\FluxIcons\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a build wrote icon components, so a package that catalogues the application's
 * icons can refresh without the developer remembering to.
 */
class IconsBuilt
{
    use Dispatchable;

    /**
     * @param  string  $vendor  The vendor's key in the flux-icons config.
     * @param  string  $namespace  The directory the icons were written to, and their prefix in `<flux:icon.{namespace}.{name}>`.
     * @param  string  $directory  The absolute path of that directory.
     * @param  list<string>  $icons  The names of the icons written, as used in `<flux:icon.{namespace}.{name}>`.
     */
    public function __construct(
        public readonly string $vendor,
        public readonly string $namespace,
        public readonly string $directory,
        public readonly array $icons,
    ) {}
}
