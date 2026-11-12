<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Font Awesome Free's brand icons, each drawn in one style, so every variant shows it.
 *
 * @see https://fontawesome.com/search?ic=brands
 *
 * A vendor of its own rather than an option of FontAwesome: brands are other icons, not another
 * style of the same ones, and an update rebuilds a vendor's icons with its class's options.
 */
class FontAwesomeBrands extends FontAwesome
{
    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir('brands'))
                ->stroke(false)
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant->basedOn('outline'))
            ->variant('mini', fn ($variant) => $variant->basedOn('outline'))
            ->variant('micro', fn ($variant) => $variant->basedOn('outline'));
    }
}
