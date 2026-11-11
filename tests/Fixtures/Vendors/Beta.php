<?php

namespace Ympact\FluxIcons\Tests\Fixtures\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * The beta fixture as a vendor class: every variant in one directory, told apart by suffix.
 */
class Beta extends Vendor
{
    public function package(): string
    {
        return 'beta';
    }

    public function basePath(): string
    {
        return 'icons';
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                ->template(Template::Outline)
                ->source(Source::dir()->suffix('_24_regular'))
                ->stroke(new Stroke(1.5, linecap: null, linejoin: null))
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir()->suffix('_24_filled'))
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }
}
