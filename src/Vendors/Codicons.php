<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * VS Code Codicons.
 *
 * @see https://microsoft.github.io/vscode-codicons/dist/codicon.html
 *
 * Codicons ships a single style, drawn as filled shapes, so every variant is built from
 * the same files.
 */
class Codicons extends Vendor
{
    public function package(): string
    {
        return '@vscode/codicons';
    }

    public function basePath(): string
    {
        return 'src/icons';
    }

    public function license(): string
    {
        return 'CC-BY-4.0';
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir())
                ->stroke(false)
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
