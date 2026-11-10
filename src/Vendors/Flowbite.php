<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Flowbite icons.
 *
 * @see https://flowbite.com/icons
 *
 * Outline and solid icons are each grouped into one subdirectory per category, which the
 * `*` in the source directory spans.
 */
class Flowbite extends Vendor
{
    public function package(): string
    {
        return 'flowbite-icons';
    }

    public function basePath(): string
    {
        return 'src';
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                ->template(Template::Outline)
                ->source(Source::dir('outline/*'))
                ->stroke(new Stroke(1.5, linecap: null, linejoin: null))
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir('solid/*'))
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }
}
