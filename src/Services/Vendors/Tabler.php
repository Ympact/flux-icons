<?php

namespace Ympact\FluxIcons\Services\Vendors;

use Ympact\FluxIcons\Types\SvgPath;
use Illuminate\Support\Collection;

class Tabler
{
    /**
     * Transform SVG Paths of the icon
     * @param string $variant (solid, outline)
     * @param string $iconName base name of the icon
     * @param Collection<SvgPath> collection of $svgPaths
     * @return string
     */
    public static function transformSvgPath($variant, $iconName, $svgPaths): string
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
     * @param float $defaultStrokeWidth 1.5 or the default set in config option `default_stroke_width`
     * @param Collection<SvgPath> $svgPaths
     * @return int|float
     */
    public static function changeStrokeWidth ($iconName, $defaultStrokeWidth, $svgPaths): int|float {
        // icons that have a small circular shape should have a stroke width of 2 otherwise you may see a gap in the icon when using 1.5
        // ie icons such as dots, dots-vertical, grip-horizontal, grip-vertical, etc
        return $svgPaths->filter(function(SvgPath $svgPath){
            return strpos($svgPath->getD(), 'a1 1 0 1 0') !== false;
        })->count() > 0 ? 2 : $defaultStrokeWidth;
    }
}