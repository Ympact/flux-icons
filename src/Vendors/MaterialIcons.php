<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Google's Material Icons.
 *
 * @see https://fonts.google.com/icons?icon.set=Material+Icons
 *
 * Published under the `material` namespace rather than its key, so its icons are written
 * as <flux:icon.material.home />. Both styles are drawn as filled shapes.
 */
class MaterialIcons extends Vendor
{
    public function package(): string
    {
        return '@material-design-icons/svg';
    }

    public function namespace(): string
    {
        return 'material';
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
                ->source(Source::dir('outlined'))
                ->stroke(false)
                // carried over from v1; the shapes are filled, so these change nothing yet
                ->attributes([
                    'stroke-linecap' => 'round',
                    'stroke-linejoin' => 'round',
                ])
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir('filled'))
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }
}
