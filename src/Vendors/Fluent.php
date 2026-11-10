<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Fluent UI System Icons.
 *
 * @see https://github.com/microsoft/fluentui-system-icons
 *
 * Every icon is drawn per size and style, as `{icon}_{size}_{regular|filled}.svg`. Each
 * variant picks the size it is shown at, so mini and micro use the 20 and 16 pixel drawings.
 */
class Fluent extends Vendor
{
    public function package(): string
    {
        return '@fluentui/svg-icons';
    }

    public function basePath(): string
    {
        return 'icons';
    }

    public function name(): string
    {
        return 'Fluent UI';
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir()->suffix('_24_regular'))
                ->stroke(false)
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir()->suffix('_24_filled'))
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant
                ->basedOn('solid')
                ->source(Source::dir()->suffix('_20_filled'))
            )
            ->variant('micro', fn ($variant) => $variant
                ->basedOn('solid')
                ->source(Source::dir()->suffix('_16_filled'))
            );
    }
}
