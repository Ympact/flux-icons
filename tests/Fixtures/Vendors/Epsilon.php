<?php

namespace Ympact\FluxIcons\Tests\Fixtures\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * The epsilon fixture as a vendor class: a prefix on every file and a suffix on outlines.
 *
 * Its v1 config also names filters and bases mini and micro on outline, but at keys v1
 * never reads, so this class leaves both out to build what v1 builds.
 */
class Epsilon extends Vendor
{
    public function package(): string
    {
        return 'epsilon';
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
                ->source(Source::dir()->prefix('icon-')->suffix('-outline'))
                ->stroke(new Stroke(1.5, linecap: null, linejoin: null))
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir()->prefix('icon-'))
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }
}
