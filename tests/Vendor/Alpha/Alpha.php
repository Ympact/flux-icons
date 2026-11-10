<?php

namespace Ympact\FluxIcons\Tests\Vendor\Alpha;

use Illuminate\Support\Collection;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Types\SvgPath;

/**
 * Callbacks for the alpha fixture vendor, mirroring the Tabler ones so a build exercises both hooks.
 */
class Alpha
{
    /**
     * @param  Collection<int, SvgPath>  $svgPaths
     * @return Collection<int, SvgPath>
     */
    public static function transform(Collection $svgPaths, Icon $icon): Collection
    {
        return $svgPaths->filter(fn (SvgPath $svgPath) => $svgPath->getD() !== 'M0 0h24v24H0z');
    }

    public static function strokeWidth(Icon $icon): int|float
    {
        $hasSmallCircle = $icon->getPaths()
            ->contains(fn (SvgPath $svgPath) => str_contains($svgPath->getD(), 'a1 1 0 1 0'));

        return $hasSmallCircle ? 2 : $icon->getStrokeWidth();
    }
}
