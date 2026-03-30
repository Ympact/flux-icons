<?php

namespace Ympact\FluxIcons\Tests\Vendor\Alpha;

use Illuminate\Support\Collection;
use Ympact\FluxIcons\Types\SvgPath;

class Alpha
{
    public static function transformSvgPath(string $variant, string $iconName, Collection $svgPaths): Collection
    {
        return $svgPaths->filter(function (SvgPath $svgPath) {
            return $svgPath->getD() !== 'M0 0h24v24H0z';
        });
    }

    public static function changeStrokeWidth(string $iconName, int|float $currentStrokeWidth, Collection $svgPaths): int|float
    {
        return $svgPaths->filter(function (SvgPath $svgPath) {
            return strpos($svgPath->getD(), 'a1 1 0 1 0') !== false;
        })->count() > 0 ? 2 : $currentStrokeWidth;
    }
}
