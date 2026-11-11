<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Env;

/**
 * The icons installed Composer packages declare they need, in their composer.json:
 *
 *     "extra": {"flux-icons": {"icons": {"tabler": ["calendar-month", "map-pin"]}}}
 *
 * A package keeps icon names in its own PHP under vendor/, which flux-icons:check does not
 * scan, so it states them instead. Each list is keyed by the namespace its references use,
 * `tabler` in `tabler.map-pin`, since a package cannot know the key an application registers a
 * vendor under. Read from vendor/composer/installed.json, as Laravel's package discovery reads
 * it, so a package needs no Composer plugin and an application no install hook.
 */
class DeclaredIcons
{
    /**
     * @param  string|null  $vendorPath  Composer's vendor directory; COMPOSER_VENDOR_DIR, else vendor/ in the application.
     */
    public function __construct(protected ?string $vendorPath = null) {}

    /**
     * The declared icons per package and namespace, leaving out anything that is not a name.
     *
     * @return array<string, array<string, list<string>>> Package name => namespace => icon names.
     */
    public function all(): array
    {
        $declared = [];

        foreach ($this->installedPackages() as $package) {
            $icons = $package['extra']['flux-icons']['icons'] ?? null;

            if (! is_array($icons) || ! is_string($package['name'] ?? null)) {
                continue;
            }

            foreach ($icons as $namespace => $names) {
                $names = is_array($names) ? array_values(array_unique(array_filter($names, fn ($name) => is_string($name) && $name !== ''))) : [];

                if (is_string($namespace) && $names !== []) {
                    $declared[$package['name']][$namespace] = $names;
                }
            }
        }

        ksort($declared);

        return $declared;
    }

    /**
     * Every icon of one namespace, whichever package declared it.
     *
     * @return list<string>
     */
    public function forNamespace(string $namespace): array
    {
        return array_values(array_unique(array_merge(...array_values(array_map(
            fn (array $namespaces) => $namespaces[$namespace] ?? [],
            $this->all()
        )))));
    }

    /**
     * Composer 2 lists the packages under `packages`; Composer 1 wrote the list itself.
     *
     * @return list<array<string, mixed>>
     */
    protected function installedPackages(): array
    {
        $file = $this->vendorPath().'/composer/installed.json';

        if (! is_file($file)) {
            return [];
        }

        $installed = json_decode((string) file_get_contents($file), true);
        $packages = is_array($installed) ? ($installed['packages'] ?? $installed) : [];

        return array_values(array_filter(is_array($packages) ? $packages : [], 'is_array'));
    }

    /**
     * Found as Laravel's PackageManifest finds it.
     */
    protected function vendorPath(): string
    {
        $configured = Env::get('COMPOSER_VENDOR_DIR');

        return $this->vendorPath ?? (is_string($configured) && $configured !== '' ? $configured : base_path('vendor'));
    }
}
