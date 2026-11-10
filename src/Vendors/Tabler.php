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
     * Tabler wraps every icon in a transparent bounding box path. The filled confetti also
     * draws its streamers as strokes, which a filled template would otherwise fill.
     *
     * @param  Collection<int, SvgPath>  $paths
     * @return Collection<int, SvgPath>
     */
    public function transform(Collection $paths, Icon $icon): Collection
    {
        $paths = $paths->filter(fn (SvgPath $path) => $path->getD() !== 'M0 0h24v24H0z');

        if ($this->isFilled($icon) && $icon->getBaseName() === 'confetti') {
            return $this->strokeAllButLast($paths);
        }

        return $paths;
    }

    /**
     * Icons built from small circular shapes show a gap at the default stroke width. Where a
     * filled variant falls back to the outline, refresh and the arrows read too light as well.
     */
    public function strokeWidth(Icon $icon, float $default): float
    {
        $hasSmallCircle = $icon->getPaths()
            ->contains(fn (SvgPath $path) => str_contains($path->getD(), 'a1 1 0 1 0'));

        if ($hasSmallCircle) {
            return 2;
        }

        $name = $icon->getBaseName() ?? '';

        if ($this->isFilled($icon) && ($name === 'refresh' || Str::startsWith($name, 'arrow-'))) {
            return 2;
        }

        return $default;
    }

    protected function isFilled(Icon $icon): bool
    {
        return in_array($icon->getVariant(), ['solid', 'mini', 'micro'], true);
    }

    /**
     * Stroke the paths v1 stroked: each whose original position is below the number of
     * paths left minus one. With the bounding box removed, that leaves the last two filled.
     *
     * @param  Collection<int, SvgPath>  $paths
     * @return Collection<int, SvgPath>
     */
    protected function strokeAllButLast(Collection $paths): Collection
    {
        $end = $paths->count() - 1;

        return $paths->map(function (SvgPath $path, int $index) use ($end) {
            if ($index < $end) {
                $path->setAttributes(['stroke' => 'currentColor', 'stroke-width' => 1.5]);
            }

            return $path;
        });
    }
}
