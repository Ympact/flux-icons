<?php

namespace Ympact\FluxIcons\Build;

use Closure;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Events\IconsBuilt;
use Ympact\FluxIcons\Variants\Variant;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Builds the Flux icon components of a vendor from its installed npm package.
 *
 * Every icon of the vendor's default variant becomes one component holding the four
 * variants Flux asks for. An icon is skipped when one of them has neither a source nor a
 * fallback. Vendor variants such as duotone reach the component with #18.
 */
class Builder
{
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
     * @param  array<int, string>|null  $icons  The icons to build, by name, file name or published
     *                                          name; null builds every icon the vendor ships.
     * @param  (Closure(string): void)|null  $progress  Called with each icon's name once it is handled.
     */
    public function build(Vendor $vendor, ?array $icons = null, ?Closure $progress = null): BuildResult
    {
        $packageRoot = $this->packagesPath.'/'.$vendor->package();
        $resolver = new SourceResolver(rtrim($packageRoot.'/'.$vendor->basePath(), '/'));
        $variants = $vendor->resolvedVariants();

        [$selected, $missing] = $this->select($vendor, $resolver->icons($variants->default()), $icons);

        $directory = $this->outputPath.'/'.$vendor->namespace();
        $credits = [$this->credits->package($packageRoot), $this->credits->flux()];
        $built = [];
        $skipped = [];

        foreach ($selected as $name => $file) {
            $markup = $this->renderVariants($vendor, $variants, $resolver, $name, $file);

            if (is_string($markup)) {
                $skipped[$name] = $markup;
            } else {
                $published = $vendor->iconName($name);
                $this->writer->write($directory, $vendor->namespace(), $published, $markup, ...$credits);
                $built[] = $published;
            }

            if ($progress !== null) {
                $progress($name);
            }
        }

        if ($built !== []) {
            IconsBuilt::dispatch($vendor->key(), $vendor->namespace(), $directory, $built);
        }

        return new BuildResult($built, $skipped, $missing);
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

            $markup[$building] = $this->renderer->render($vendor, $source[0], $building, $source[1], $name);
        }

        return $markup;
    }

    /**
     * The variant to render an icon with and the file to render it from, following a fallback
     * once. A fallback to the default variant reuses the file the icon was listed from: an
     * MDI icon that is its own outline has no `-outline` file to look up by name.
     *
     * @return array{0: Variant, 1: string}|null
     */
    protected function source(VariantSet $variants, SourceResolver $resolver, Variant $variant, string $name, string $file): ?array
    {
        $default = $variants->defaultName();

        if ($variant->getName() === $default) {
            return [$variant, $file];
        }

        if ($found = $resolver->file($variant, $name)) {
            return [$variant, $found];
        }

        $fallback = $variants->fallbackFor($variant->getName());

        if ($fallback === null) {
            return null;
        }

        if ($fallback->getName() === $default) {
            return [$fallback, $file];
        }

        $found = $resolver->file($fallback, $name);

        return $found ? [$fallback, $found] : null;
    }
}
