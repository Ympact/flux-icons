<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Lucide icons.
 *
 * @see https://lucide.dev
 *
 * Lucide only ships stroked outlines, so every variant is built from them and keeps the
 * adjustable stroke.
 */
class Lucide extends Vendor
{
    public function package(): string
    {
        return 'lucide-static';
    }

    public function basePath(): string
    {
        return 'icons';
    }

    public function license(): string
    {
        return 'ISC';
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                ->template(Template::Outline)
                ->source(Source::dir())
                // the stroke sets no caps or joins: Lucide declares round ones on its own root,
                // which the build keeps. v1 replaced that root and drew butt caps
                ->stroke(new Stroke(1.5, linecap: null, linejoin: null))
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->basedOn('outline')
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }
}
