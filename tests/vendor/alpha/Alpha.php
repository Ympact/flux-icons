<?php

namespace Ympact\FluxIcons\Tests\Vendor\Alpha;


use Ympact\FluxIcons\Types\SvgPath;
use Illuminate\Support\Collection;

class Alpha
{
    /**
     * Transform SVG Paths of the icon
     * @param string $variant (solid, outline)
     * @param string $iconName base name of the icon
     * @param Collection<SvgPath> collection of $svgPaths
     * @return string
     */
    public static function transformSvgPath(string $variant, string $iconName, Collection $svgPaths): Collection
    {
        // remove the first $svgPath from the array that has a d attribute of 'M0 0h24v24H0z'
        $svgPaths = $svgPaths->filter(function(SvgPath $svgPath){
            return $svgPath->getD() !== 'M0 0h24v24H0z';
        });

        return $svgPaths;
    }

    /**
     * Adjust the stroke width of the outline icon
     * @param string $iconName base name of the icon
     * @param int|float $currentStrokeWidth 1.5 or the default set in config option `default_stroke_width`
     * @param Collection<SvgPath> $svgPaths
     * @return int|float
     */
    public static function changeStrokeWidth (string $iconName, int|float $currentStrokeWidth, Collection $svgPaths): int|float {
        // icons that have a small circular shape should have a stroke width of 2 otherwise you may see a gap in the icon when using 1.5
        // ie icons such as dots, dots-vertical, grip-horizontal, grip-vertical, etc
        return $svgPaths->filter(function(SvgPath $svgPath){
            return strpos($svgPath->getD(), 'a1 1 0 1 0') !== false;
        })->count() > 0 ? 2 : $currentStrokeWidth;
    }
}