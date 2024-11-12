<?php

namespace Ympact\FluxIcons\Services\Vendors;

use Ympact\FluxIcons\Types\SvgPath;
use Illuminate\Support\Collection;
use Ympact\FluxIcons\Types\Icon;

class Tabler
{
    /**
     * Transform SVG Paths of the icon
     * @param string $variant (solid, outline)
     * @param string $iconName base name of the icon
     * @param Collection<SvgPath> collection of $svgPaths
     * @return string
     */
    public static function transform($variant, $iconName, $svgPaths): Collection
    {
        // remove the first $svgPath from the array that has a d attribute of 'M0 0h24v24H0z'
        $svgPaths = $svgPaths->filter(function(SvgPath $svgPath){
            return $svgPath->getD() !== 'M0 0h24v24H0z';
        });

        // for some solid icons we want to transform parts of the paths
        if($variant === 'solid' || $variant === 'mini' || $variant === 'micro'){
            $svgPaths = match($iconName){
                'confetti' => self::setPathAsStroke($svgPaths, 0, -1),
                default => $svgPaths
            };
        }

        return $svgPaths;
    }

    public static function attributes(Icon $icon)
    {
        $attributes = [];
        $variant = $icon->getVariant();

        if($variant == 'solid' || $variant == 'mini' || $variant == 'micro'){
            $attributes = match($icon->getBaseName()){
                'refresh' => array_merge($icon->getDefaultAttributes('outline'), ['stroke-width' => 2]),
                default => $attributes
            };
        }

        return $attributes;
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
        $strokeWidth = $svgPaths->filter(function(SvgPath $svgPath){
            return strpos($svgPath->getD(), 'a1 1 0 1 0') !== false;
        })->count() > 0 ? 2 : $currentStrokeWidth;

        //$strokeWidth = match($iconName){
        //    'refresh' => 2,
        //    default => $strokeWidth
        //};
        
        return $strokeWidth;
    }


    /**
     * add stroke="currentColor" stroke-width="1.5" attributes to the svg paths
     * @param \Illuminate\Support\Collection $svgPaths
     * @param int $start
     * @param mixed $end
     * @return Collection
     */
    private static function setPathAsStroke(Collection $svgPaths, int $start = 0, ?int $end){
        // use the start and end index to set the stroke attribute
        $total = $svgPaths->count();
        if($end < 0){
            $end = $total + $end;
        }
        return $svgPaths->map(function(SvgPath $svgPath, $index) use ($start, $end){
            if($index >= $start && $index <= $end){
                $svgPath->setAttributes([
                    'stroke' => 'currentColor',
                    'stroke-width' => 1.5
                ]);
            }
            return $svgPath;
        });
    }
}