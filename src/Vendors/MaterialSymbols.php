<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Google's Material Symbols, at weight 300 in the outlined style.
 *
 * @see https://fonts.google.com/icons?icon.set=Material+Symbols
 *
 * Outline and filled icons share one directory, the filled ones carrying `-fill` in their
 * name. Both are drawn as filled shapes.
 */
class MaterialSymbols extends Vendor
{
    public function package(): string
    {
        return '@material-symbols/svg-300';
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
                ->source(Source::dir('outlined')->filter(fn (string $file) => ! $this->isFilled($file)))
                ->stroke(false)
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir('outlined')->suffix('-fill')->filter(fn (string $file) => $this->isFilled($file)))
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }

    protected function isFilled(string $file): bool
    {
        return str_contains(pathinfo($file, PATHINFO_FILENAME), '-fill');
    }
}
