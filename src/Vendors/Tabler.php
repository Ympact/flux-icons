<?php

namespace Ympact\FluxIcons\Vendors;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Types\SvgPath;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Tabler icons.
 *
 * @see https://tabler.io/icons
 *
 * The reference vendor: outline icons carry an adjustable stroke, a subset of
 * the icons also ships a filled version, and mini/micro derive from solid.
 */
class Tabler extends Vendor
{
    public function package(): string
    {
        return '@tabler/icons';
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
                ->template(Template::Outline)
                ->source(Source::dir('outline'))
                ->stroke(new Stroke(1.5))
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir('filled'))
                ->stroke(false)
                ->attributes([
                    'fill-rule' => 'evenodd',
                    'clip-rule' => 'evenodd',
                ])
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }

    /**
     * Tabler wraps every icon in a transparent bounding box path.
     *
     * @param  Collection<int, SvgPath>  $paths
     * @return Collection<int, SvgPath>
     */
    public function transform(Collection $paths, Icon $icon): Collection
    {
        return $paths->filter(fn (SvgPath $path) => $path->getD() !== 'M0 0h24v24H0z');
    }

    /**
     * Icons built from small circular shapes show a gap at the default stroke
     * width, and arrows read too light.
     */
    public function strokeWidth(Icon $icon, float $default): float
    {
        $hasSmallCircle = $icon->getPaths()
            ->contains(fn (SvgPath $path) => str_contains($path->getD(), 'a1 1 0 1 0'));

        if ($hasSmallCircle) {
            return 2;
        }

        if (Str::startsWith($icon->getBaseName() ?? '', 'arrow-')) {
            return 2;
        }

        return $default;
    }
}
