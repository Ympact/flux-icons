<?php

namespace Ympact\FluxIcons\Tests\Fixtures\Vendors;

use Illuminate\Support\Collection;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Types\SvgPath;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * The alpha fixture as a vendor class: outline and filled directories, with Tabler-like hooks.
 */
class Alpha extends Vendor
{
    public function package(): string
    {
        return 'alpha';
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
                ->source(Source::dir('outline'))
                ->stroke(new Stroke(1.5, linecap: null, linejoin: null))
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

    /**
     * @param  Collection<int, SvgPath>  $paths
     * @return Collection<int, SvgPath>
     */
    public function transform(Collection $paths, Icon $icon): Collection
    {
        return $paths->filter(fn (SvgPath $path) => $path->getD() !== 'M0 0h24v24H0z');
    }

    public function strokeWidth(Icon $icon, float $default): float
    {
        $hasSmallCircle = $icon->getPaths()->contains(fn (SvgPath $path) => str_contains($path->getD(), 'a1 1 0 1 0'));

        return $hasSmallCircle ? 2 : $default;
    }
}
