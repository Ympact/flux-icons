<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Finds references to this package's icons in Blade templates and PHP sources, and reports
 * the ones with no icon behind them.
 *
 * Flux resolves an icon to a Blade component and throws when it does not exist, so a
 * reference to an icon that was never built is a 500 on whichever page renders it, and one
 * that only shows up when that page is rendered. Neither the compiler nor a test suite will
 * point at it, which is why this walks the sources instead.
 *
 * Only namespaced references are examined. A bare `icon="check"` is a Flux built-in and
 * none of this package's business; `icon="tabler.check"` is. A reference inside a comment
 * is dead code and is left out. In PHP a reference is a string literal that holds nothing
 * but the name, and not a key handed to `config()`, `__()`, `view()` and the like, which
 * look like one: `config('flags.new_checkout')`.
 */
class IconReferenceScanner
{
    /** Functions and methods whose first argument is a key, such as a config or translation key. */
    protected const KEY_FUNCTIONS = ['config', '__', 'trans', 'trans_choice', 'view', 'route', 'to_route', 'lang'];

    /** Facades whose methods take a key, such as Config::get('flags.new_checkout'). */
    protected const KEY_CLASSES = ['config', 'lang', 'view', 'route', 'trans'];

    /**
     * Vendor namespaces to look for.
     *
     * @var array<int, string>
     */
    protected array $namespaces;

    /**
     * @param  iterable<string>  $namespaces
     * @param  string|null  $iconsPath  Where the icons are built, which is not scanned: each built
     *                                  icon names itself in its header. Defaults to resources/views/flux/icon.
     */
    public function __construct(iterable $namespaces, protected ?string $iconsPath = null)
    {
        $this->namespaces = collect($namespaces)
            ->map(fn (string $namespace) => trim($namespace))
            ->filter(fn (string $namespace) => $namespace !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Scan the PHP and Blade files in the given directories for icon references.
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

        $finder = Finder::create()->files()->name('*.php')->in($directories->all());
        $built = $this->iconsPath();

        return collect(iterator_to_array($finder, false))
            // keyed by real path, so a directory reached through two scanned paths counts once
            ->keyBy(fn (SplFileInfo $file) => $file->getRealPath() ?: $file->getPathname())
            ->reject(fn (SplFileInfo $file, string $path) => $built !== null && str_starts_with($path, $built.DIRECTORY_SEPARATOR))
            ->flatMap(fn (SplFileInfo $file) => $this->referencesIn($file))
            ->values();
    }

    /**
     * @return Collection<int, IconReference>
     */
    protected function referencesIn(SplFileInfo $file): Collection
    {
        $contents = (string) file_get_contents($file->getPathname());
        $blade = str_ends_with($file->getFilename(), '.blade.php');
        $code = $blade ? SourceMask::blade($contents) : $contents;

        return collect($this->namespaces)
            ->flatMap(fn (string $namespace) => $blade ? $this->matches($code, $namespace) : $this->phpMatches($code, $namespace))
            ->sortBy('offset')
            ->map(fn (array $match) => new IconReference(
                file: $file->getPathname(),
                line: 1 + substr_count(substr($contents, 0, $match['offset']), "\n"),
                namespace: $match['namespace'],
                icon: $match['icon'],
                variant: $blade ? $this->literalVariant($code, $match['offset']) : null,
                iconsPath: $this->iconsPath,
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

    /**
     * The references in a PHP file: string literals that hold nothing but the name, and names
     * in its inline HTML as a template has them.
     *
     * @return list<array{namespace: string, icon: string, offset: int}>
     */
    protected function phpMatches(string $contents, string $namespace): array
    {
        $exact = '/^'.preg_quote($namespace, '/').'\.((?:'.preg_quote($namespace, '/').'\.)?[A-Za-z0-9][A-Za-z0-9_-]*)$/';
        $matches = [];
        $offset = 0;
        $before = [];

        foreach (token_get_all($contents) as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $type = is_array($token) ? $token[0] : null;

            if ($type === T_INLINE_HTML) {
                foreach ($this->matches($text, $namespace) as $match) {
                    $matches[] = [...$match, 'offset' => $match['offset'] + $offset];
                }
            } elseif ($type === T_CONSTANT_ENCAPSED_STRING && preg_match($exact, substr($text, 1, -1), $icon) && ! $this->isKeyArgument($before)) {
                $matches[] = ['namespace' => $namespace, 'icon' => $icon[1], 'offset' => $offset + 1];
            }

            if (! in_array($type, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $before = [...array_slice($before, -3), $token];
            }

            $offset += strlen($text);
        }

        return $matches;
    }

    /**
     * Whether the tokens before a string make it the first argument of a call that takes a key.
     *
     * @param  list<array{0: int, 1: string, 2: int}|string>  $before  Up to four tokens, the nearest last.
     */
    protected function isKeyArgument(array $before): bool
    {
        [$class, $operator, $name, $parenthesis] = array_pad(array_map(
            fn ($token) => strtolower(is_array($token) ? $token[1] : $token),
            $before
        ), -4, '');

        if ($parenthesis !== '(') {
            return false;
        }

        return in_array($name, self::KEY_FUNCTIONS, true)
            || ($operator === '::' && in_array(class_basename($class), self::KEY_CLASSES, true));
    }

    /**
     * The variant a `<flux:icon>` tag around the offset asks for, when it is written as a
     * literal. Only that tag's variant is the icon's: on `<flux:button icon="...">` it is the
     * button's, and a bound or echoed one is only known when the page renders.
     */
    protected function literalVariant(string $contents, int $offset): ?string
    {
        $start = strrpos(substr($contents, 0, $offset), '<');
        $end = strpos($contents, '>', $offset);

        // a tag that closed before the reference is not the one it sits in
        if ($start === false || $end === false || str_contains(substr($contents, $start, $offset - $start), '>')) {
            return null;
        }

        $tag = substr($contents, $start, $end - $start);

        if (! preg_match('/^<flux:icon[\s.]/', $tag) || ! preg_match('/(?<![\w:.-])variant\s*=\s*(["\'])([\w-]+)\1/', $tag, $variant)) {
            return null;
        }

        return $variant[2];
    }

    protected function iconsPath(): ?string
    {
        $path = $this->iconsPath ?? resource_path('views/flux/icon');

        return realpath($path) ?: null;
    }
}
