<?php

namespace Ympact\FluxIcons\Build;

use Closure;
use RuntimeException;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Events\IconsBuilt;
use Ympact\FluxIcons\Services\PackageManager;
use Ympact\FluxIcons\Types\InvalidSvg;
use Ympact\FluxIcons\Variants\Variant;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Builds the Flux icon components of a vendor from its installed npm package.
 *
 * Every icon of the vendor's default variant becomes one component holding the four
 * variants Flux asks for. An icon is skipped when one of them has neither a source nor a
 * fallback, or when an earlier icon took its published name. Vendor variants such as
 * duotone reach the component with #18.
 */
class Builder
{
    /** The first Flux version whose icon components take the `@blaze` directive. */
    protected const BLAZE_SINCE = '2.2.6';

    protected string $packagesPath;

    protected string $outputPath;

    public function __construct(
        ?string $packagesPath = null,
        ?string $outputPath = null,
        protected IconRenderer $renderer = new IconRenderer,
        protected ComponentWriter $writer = new ComponentWriter,
        protected Credits $credits = new Credits,
    ) {
        $this->packagesPath = $packagesPath ?? base_path('node_modules');
        $this->outputPath = $outputPath ?? resource_path('views/flux/icon');
    }

    /**
     * Every icon the vendor ships, as icon name to the file of its default variant.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException When the vendor's npm package is not installed.
     */
    public function available(Vendor $vendor): array
    {
        return $this->resolver($vendor)->icons($vendor->resolvedVariants()->default());
    }

    /**
     * @param  array<int, string>|null  $icons  The icons to build, by name, file name or published
     *                                          name; null builds every icon the vendor ships.
     * @param  (Closure(string, int, int): void)|null  $progress  Called once each icon is handled, with
     *                                                            its name, the number handled and the total.
     *
     * @throws RuntimeException When the vendor's npm package is not installed.
     */
    public function build(Vendor $vendor, ?array $icons = null, ?Closure $progress = null): BuildResult
    {
        $packageRoot = $this->packagesPath.'/'.$vendor->package();
        $resolver = $this->resolver($vendor);
        $variants = $vendor->resolvedVariants();

        [$selected, $missing] = $this->select($vendor, $resolver->icons($variants->default()), $icons);

        $directory = $this->outputPath.'/'.$vendor->namespace();
        $header = [$this->credits->package($packageRoot), $this->credits->flux(), PackageManager::fluxVersionAtLeast(self::BLAZE_SINCE)];
        $publishedFrom = [];
        $skipped = [];
        $handled = 0;

        foreach ($selected as $name => $file) {
            $published = $vendor->iconName($name);

            // iconName() can map two files to one name; the first keeps it rather than being overwritten
            $markup = isset($publishedFrom[$published])
                ? "{$publishedFrom[$published]} is already published as {$published}"
                : $this->renderVariants($vendor, $variants, $resolver, $name, $file);

            if (is_string($markup)) {
                $skipped[$name] = $markup;
            } else {
                $this->writer->write($directory, $vendor->namespace(), $published, $markup, ...$header);
                $publishedFrom[$published] = $name;
            }

            if ($progress !== null) {
                $progress($name, ++$handled, count($selected));
            }
        }

        $built = array_map('strval', array_keys($publishedFrom));

        if ($built !== []) {
            IconsBuilt::dispatch($vendor->key(), $vendor->namespace(), $directory, $built);
        }

        return new BuildResult($built, $skipped, $missing);
    }

    /**
     * @throws RuntimeException When the vendor's npm package is not installed.
     */
    protected function resolver(Vendor $vendor): SourceResolver
    {
        $packageRoot = $this->packagesPath.'/'.$vendor->package();

        if (! is_dir($packageRoot)) {
            throw new RuntimeException("The npm package {$vendor->package()} is not installed in {$this->packagesPath}.");
        }

        return new SourceResolver(rtrim($packageRoot.'/'.$vendor->basePath(), '/'));
    }

    /**
     * The icons to build, and the requested ones the vendor does not ship.
     *
     * @param  array<string, string>  $available
     * @param  array<int, string>|null  $requested
     * @return array{0: array<string, string>, 1: list<string>}
     */
    protected function select(Vendor $vendor, array $available, ?array $requested): array
    {
        if ($requested === null) {
            return [$available, []];
        }

        $selected = array_filter(
            $available,
            fn (string $file, string $name) => array_intersect([$name, pathinfo($file, PATHINFO_FILENAME), $vendor->iconName($name)], $requested) !== [],
            ARRAY_FILTER_USE_BOTH
        );

        $found = array_merge(
            array_keys($selected),
            array_map(fn (string $file) => pathinfo($file, PATHINFO_FILENAME), $selected),
            array_map(fn (string $name) => $vendor->iconName($name), array_keys($selected)),
        );

        return [$selected, array_values(array_diff($requested, $found))];
    }

    /**
     * The markup of every Flux variant, or why the icon cannot be built.
     *
     * @return array<string, string>|string
     */
    protected function renderVariants(Vendor $vendor, VariantSet $variants, SourceResolver $resolver, string $name, string $file): array|string
    {
        $markup = [];

        foreach ($variants->fluxVariants() as $building => $variant) {
            $source = $this->source($variants, $resolver, $variant, $name, $file);

            if ($source === null) {
                return "no source for its {$building} variant";
            }

            try {
                $markup[$building] = $this->renderer->render($vendor, $source[0], $building, $source[1], $name);
            } catch (InvalidSvg $invalid) {
                return $invalid->getMessage();
            }
        }

        return $markup;
    }

    /**
     * The variant to render an icon with and the file to render it from, following fallbacks
     * until one has the icon. The default variant reuses the file the icon was listed from: an
     * MDI icon that is its own outline has no `-outline` file to look up by name.
     *
     * @param  list<string>  $tried  The variants already looked in, which ends a fallback cycle.
     * @return array{0: Variant, 1: string}|null
     */
    protected function source(VariantSet $variants, SourceResolver $resolver, Variant $variant, string $name, string $file, array $tried = []): ?array
    {
        if ($variant->getName() === $variants->defaultName()) {
            return [$variant, $file];
        }

        if ($found = $resolver->file($variant, $name)) {
            return [$variant, $found];
        }

        $fallback = $variants->fallbackFor($variant->getName());
        $tried[] = $variant->getName();

        if ($fallback === null || in_array($fallback->getName(), $tried, true)) {
            return null;
        }

        return $this->source($variants, $resolver, $fallback, $name, $file, $tried);
    }
}
