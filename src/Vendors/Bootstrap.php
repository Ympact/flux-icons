<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Bootstrap icons.
 *
 * @see https://icons.getbootstrap.com
 *
 * Outline and filled icons share one directory, the filled ones carrying `-fill` in their
 * name. The outline icons are drawn as filled shapes, so both use the solid template.
 */
class Bootstrap extends Vendor
{
    public function package(): string
    {
        return 'bootstrap-icons';
    }

    public function basePath(): string
    {
        return 'icons';
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
                ->source(Source::dir()->filter(fn (string $file) => ! $this->isFilled($file)))
                ->stroke(false)
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir()->suffix('-fill')->filter(fn (string $file) => $this->isFilled($file)))
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
