<?php

namespace Ympact\FluxIcons\Vendors;

use Illuminate\Support\Str;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Material Design Icons by Pictogrammers.
 *
 * @see https://pictogrammers.com/library/mdi
 *
 * All icons share one directory. An icon is solid when an `-outline` sibling exists, and
 * that sibling is its outline; an icon without one is its own outline.
 */
class Mdi extends Vendor
{
    public function package(): string
    {
        return '@mdi/svg';
    }

    public function basePath(): string
    {
        return 'svg';
    }

    public function name(): string
    {
        return 'Material Design Icons';
    }

    public function license(): string
    {
        return 'Apache-2.0';
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir()->suffix('-outline')->filter(fn (string $file) => $this->isOutline($file)))
                ->stroke(false)
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir()->filter(fn (string $file) => ! $this->isOutline($file)))
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }

    protected function isOutline(string $file): bool
    {
        return str_contains(pathinfo($file, PATHINFO_FILENAME), '-outline')
            || ! is_file(Str::beforeLast($file, '.svg').'-outline.svg');
    }

    /**
     * meta.json lists each icon with its `aliases`, the names it is also searched by. Its `tags`
     * are categories, such as "Developer / Languages", rather than search terms.
     *
     * @return array<array-key, list<string>>
     */
    public function tags(string $packageRoot): array
    {
        return collect($this->readJson("{$packageRoot}/meta.json"))
            ->filter(fn ($icon) => is_array($icon) && is_string($icon['name'] ?? null))
            ->mapWithKeys(fn (array $icon) => [$icon['name'] => $this->terms($icon['aliases'] ?? null)])
            ->all();
    }
}
