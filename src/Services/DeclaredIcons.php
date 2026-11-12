<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Env;

/**
 * The icons installed Composer packages declare in their composer.json: the ones their views
 * require, and optional ones, such as icons a user may pick.
 *
 *     "extra": {"flux-icons": {
 *         "icons": {"tabler": ["calendar-month", "map-pin"]},
 *         "optional": {"tabler": ["chart-bar"]}
 *     }}
 *
 * A package keeps icon names in its own PHP under vendor/, which flux-icons:check does not
 * scan, so it states them instead. Each list is keyed by the namespace its references use,
 * `tabler` in `tabler.map-pin`, since a package cannot know the key an application registers a
 * vendor under. Read from vendor/composer/installed.json, as Laravel's package discovery reads
 * it, so a package needs no Composer plugin and an application no install hook.
 *
 * The application leaves declarations out per package in the `packages` config: the optional
 * icons, the required ones too, as when it overrides the package's views, or single icons.
 */
class DeclaredIcons
{
    /**
     * What a namespace or an icon name can be: the declarations reach the console and the build
     * command check suggests, so anything else, such as `x$(touch pwned)`, is left out.
     */
    protected const NAME = '/^[A-Za-z0-9][A-Za-z0-9_-]*$/';

    /** The keys of the extra.flux-icons block, and whether the icons under each are required. */
    protected const LISTS = ['icons' => true, 'optional' => false];

    /**
     * @param  string|null  $vendorPath  Composer's vendor directory; COMPOSER_VENDOR_DIR, else vendor/ in the application.
     * @param  array<string, mixed>|null  $packages  What to leave out, by package; the `packages` config when null.
     */
    public function __construct(
        protected ?string $vendorPath = null,
        protected ?array $packages = null,
    ) {}

    /**
     * The declared icons the application takes, per package and namespace.
     *
     * @return array<string, array<string, list<string>>> Package name => namespace => icon names.
     */
    public function all(): array
    {
        $declared = [];

        foreach ($this->declarations() as $declaration) {
            if ($declaration['included']) {
                $declared[$declaration['package']][$declaration['namespace']][] = $declaration['icon'];
            }
        }

        return $declared;
    }

    /**
     * Every icon of one namespace the application takes, whichever package declared it.
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
     * The icons a package requires that the application leaves out. The package's views may
     * fail where they use one, unless the application overrides them.
     *
     * @return list<array{package: string, namespace: string, icon: string}>
     */
    public function leftOutRequired(): array
    {
        $leftOut = array_filter($this->declarations(), fn (array $declaration) => $declaration['required'] && ! $declaration['included']);

        return array_values(array_map(
            fn (array $declaration) => ['package' => $declaration['package'], 'namespace' => $declaration['namespace'], 'icon' => $declaration['icon']],
            $leftOut
        ));
    }

    /**
     * Every declaration, by package, trimmed as the build trims names and leaving out anything
     * that is not a name. An icon a package lists as both required and optional is required.
     *
     * @return list<array{package: string, namespace: string, icon: string, required: bool, included: bool}>
     */
    public function declarations(): array
    {
        $packages = [];

        foreach ($this->installedPackages() as $package) {
            $name = $package['name'] ?? null;
            $block = $package['extra']['flux-icons'] ?? null;

            if (! is_string($name) || ! is_array($block)) {
                continue;
            }

            foreach (self::LISTS as $list => $required) {
                foreach ($this->names($block[$list] ?? null) as $namespace => $icons) {
                    foreach ($icons as $icon) {
                        $packages[$name]["{$namespace}.{$icon}"] ??= [
                            'package' => $name,
                            'namespace' => $namespace,
                            'icon' => $icon,
                            'required' => $required,
                            'included' => $this->includes($name, "{$namespace}.{$icon}", $required),
                        ];
                    }
                }
            }
        }

        ksort($packages);

        return array_merge(...array_values(array_map('array_values', $packages)));
    }

    /**
     * The valid names of one list, by namespace.
     *
     * @return array<string, list<string>>
     */
    protected function names(mixed $list): array
    {
        $names = [];

        foreach (is_array($list) ? $list : [] as $namespace => $icons) {
            $icons = array_values(array_unique(array_filter(
                array_map(fn ($icon) => is_string($icon) ? trim($icon) : '', is_array($icons) ? $icons : []),
                fn (string $icon) => preg_match(self::NAME, $icon) === 1
            )));

            if (is_string($namespace) && preg_match(self::NAME, $namespace) === 1 && $icons !== []) {
                $names[$namespace] = $icons;
            }
        }

        return $names;
    }

    /**
     * Whether the application takes a declared icon. `false` for a package leaves out all of its
     * icons, `['optional' => false]` its optional ones, `['required' => false]` its required ones,
     * and `['except' => ['tabler.map-pin']]` single icons.
     */
    protected function includes(string $package, string $name, bool $required): bool
    {
        $settings = ($this->packages ?? (array) config('flux-icons.packages', []))[$package] ?? [];

        if ($settings === false) {
            return false;
        }

        if (! is_array($settings)) {
            return true;
        }

        return ($settings[$required ? 'required' : 'optional'] ?? true) !== false
            && ! in_array($name, (array) ($settings['except'] ?? []), true);
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
