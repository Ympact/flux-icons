<?php

namespace Ympact\FluxIcons\Services\Vendors;

use Illuminate\Support\Str;
use Ympact\FluxIcons\Types\SvgPath;
use Illuminate\Support\Collection;
use Ympact\FluxIcons\Types\Icon;
use function Ympact\FluxIcons\arrayMergeRecursive;

class Tabler
{
    /**
     * Transform SVG Paths of the icon
     * @param \Ympact\FluxIcons\Types\Icon $icon
     * @return string
     */
    public static function transform(Collection $svgPaths, Icon $icon): Collection
    {

        // remove the first $svgPath from the array that has a d attribute of 'M0 0h24v24H0z'
        $svgPaths = $svgPaths->filter(function(SvgPath $svgPath){
            return $svgPath->getD() !== 'M0 0h24v24H0z';
        });

        $variant = $icon->getVariant();
        // for some solid icons we want to transform parts of the paths
        if($variant === 'solid' || $variant === 'mini' || $variant === 'micro'){
            $svgPaths = match($icon->getBaseName()){
                'confetti' => self::setPathAsStroke($svgPaths, 0, -1),
                default => $svgPaths
            };
        }

        return $svgPaths;
    }

    /**
     * Adjust the SVG attributes of the icon
     * @param \Ympact\FluxIcons\Types\Icon $icon
     * @return array
     */
    public static function attributes(Icon $icon)
    {
        $attributes = [];
        $variant = $icon->getVariant();

        if($variant == 'solid' || $variant == 'mini' || $variant == 'micro'){
            $attributes = match($icon->getBaseName()){
                //'refresh' => array_merge($icon->getDefaultAttributes('outline'), ['stroke-width' => 2]),
                //'refresh' => arrayMergeRecursive($icon->getAttributes(), ['stroke-width' => 2]),
                default => $attributes
            };
        }

        return $attributes;
    }

    /**
     * Adjust the stroke width of the icon
     * @param Icon $icon base name of the icon
     * @return int|float
     */
    public static function strokeWidth (Icon $icon): int|float {
        $strokeWidth = $icon->getStrokeWidth();

        if($icon->getTemplate() !== 'outline'){
            return $strokeWidth;
        }

        // icons that have a small circular shape should have a stroke width of 2 otherwise you may see a gap in the icon when using 1.5
        // ie icons such as dots, dots-vertical, grip-horizontal, grip-vertical, etc
        $strokeWidth = $icon->getPaths()->filter(function(SvgPath $svgPath){
            return strpos($svgPath->getD(), 'a1 1 0 1 0') !== false;
        })->count() > 0 ? 2 : $icon->getStrokeWidth();

        $variant = $icon->getVariant();
        if($variant === 'solid' || $variant === 'mini' || $variant === 'micro'){
            $strokeWidth = match($icon->getBaseName()){
                'refresh' => 2,
                default => $strokeWidth
            };

            $strokeWidth = match(true){
                Str::startsWith($icon->getBaseName(), 'arrow-') => 2,
                default => $strokeWidth
            };

        }

        // in case it is a float, make sure we have max 2 decimal places
        if(is_float($strokeWidth)){
            $strokeWidth = round($strokeWidth, 2);
        }

        return $strokeWidth;
    }


    /**
     * Helper method: add stroke="currentColor" stroke-width="1.5" attributes to the the svg paths within range
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