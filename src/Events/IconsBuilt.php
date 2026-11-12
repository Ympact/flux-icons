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
     * @param  array<array-key, list<string>>  $tags  The search terms the vendor's package publishes for
     *                                                the icons written, by name as in $icons; an icon without
     *                                                any is absent. A numeric name, such as Font Awesome's `7`, is an
     *                                                int key. All of Tabler's icons carry about 1 MB of terms, so a
     *                                                queued listener should not serialise the event whole.
     */
    public function __construct(
        public readonly string $vendor,
        public readonly string $namespace,
        public readonly string $directory,
        public readonly array $icons,
        public readonly array $tags = [],
    ) {}
}
