<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Finds references to this package's icons in Blade templates and reports the ones with no
 * icon behind them.
 *
 * Flux resolves an icon to a Blade component and throws when it does not exist, so a
 * reference to an icon that was never built is a 500 on whichever page renders it, and one
 * that only shows up when that page is rendered. Neither the compiler nor a test suite will
 * point at it, which is why this walks the templates instead.
 *
 * Only namespaced references are examined. A bare `icon="check"` is a Flux built-in and
 * none of this package's business; `icon="tabler.check"` is.
 */
class IconReferenceScanner
{
    /**
     * Vendor namespaces to look for.
     *
     * @var array<int, string>
     */
    protected array $namespaces;

    /**
     * @param  iterable<string>  $namespaces
     */
    public function __construct(iterable $namespaces)
    {
        $this->namespaces = collect($namespaces)
            ->map(fn (string $namespace) => trim($namespace))
            ->filter(fn (string $namespace) => $namespace !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Scan the given directories for icon references.
     *
     * @param  array<int, string>  $paths
     * @return Collection<int, IconReference>
     */
    public function scan(array $paths): Collection
    {
        $directories = collect($paths)->filter(fn (string $path) => is_dir($path))->values();

        if ($directories->isEmpty() || $this->namespaces === []) {
            return collect();
        }

        $finder = Finder::create()->files()->name('*.blade.php')->in($directories->all());

        return collect(iterator_to_array($finder, false))
            ->flatMap(fn (SplFileInfo $file) => $this->referencesIn($file))
            ->values();
    }

    /**
     * @return Collection<int, IconReference>
     */
    protected function referencesIn(SplFileInfo $file): Collection
    {
        $contents = (string) file_get_contents($file->getPathname());

        return collect($this->namespaces)
            ->flatMap(fn (string $namespace) => $this->matches($contents, $namespace))
            ->map(fn (array $match) => new IconReference(
                file: $file->getPathname(),
                line: 1 + substr_count(substr($contents, 0, $match['offset']), "\n"),
                namespace: $match['namespace'],
                icon: $match['icon'],
            ));
    }

    /**
     * Every `namespace.icon` occurrence, with the offset it was found at.
     *
     * The icon is captured greedily enough to include a stray dot, so that a doubled prefix
     * such as `tabler.tabler.check` is reported rather than quietly not matching.
     *
     * @return array<int, array{namespace: string, icon: string, offset: int}>
     */
    protected function matches(string $contents, string $namespace): array
    {
        // A preceding dot is allowed, because the tag form is written `flux:icon.tabler.check`.
        // A doubled prefix still reports once rather than twice: the capture is greedy, so
        // `tabler.tabler.check` is consumed whole by the first match.
        $pattern = '/(?<![\w\-\/])'.preg_quote($namespace, '/').'\.([A-Za-z0-9][A-Za-z0-9._-]*)/';

        if (! preg_match_all($pattern, $contents, $found, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $matches = [];

        foreach ($found[1] as $index => [$icon, $offset]) {
            // A credit line such as https://tabler.io/icons is not a reference to an icon.
            if (Str::startsWith(substr($contents, $offset + strlen($icon), 1), '/')) {
                continue;
            }

            $matches[] = [
                'namespace' => $namespace,
                'icon' => rtrim($icon, '.'),
                'offset' => $found[0][$index][1],
            ];
        }

        return $matches;
    }
}
