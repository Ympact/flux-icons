<?php

namespace Ympact\FluxIcons\Services\Vendors;

use DOMDocument;
use Illuminate\Support\Collection;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Types\SvgPath;

class Fluent
{
    /**
     * Determine the correct suffix for the solid icon
     * @param \Ympact\FluxIcons\Types\Icon $icon
     * @param string $variant
     * @return string
     */
    public static function sourceSolidSuffix(string $variant = null): string
    {
        $size = match($variant){
            'solid' => 24,
            'mini' => 20,
            'micro' => 16,
            default => 24
        };
        return "_{$size}_filled";
    }


    public static function transform(Collection $svgPaths, Icon $icon)
    {
        $variant = $icon->getVariant();
        if($variant == 'solid' || $variant == 'mini' || $variant == 'micro'){
            
            // split the paths
            //$svgPaths = $svgPaths->each(function(SvgPath $svgPath){
            //    $subPaths = self::splitSvgPaths($svgPath);
            //    return $subPaths;
            //});

            //$svgPaths = match($icon->getBaseName()){
            //    'add-circle' => self::filter($svgPaths, d: 'M0 0h24v24H0z'),
            //    'alert_on' => $svgPaths->filter(function(SvgPath $svgPath){
            //        return $svgPath->getD() !== 'M0 0h24v24H0z';
            //    }),
            //    default => $svgPaths
            //};
        }

        return $svgPaths;
    }

    private static function splitSvgPaths(SvgPath $path): Collection
    {
        // split the path by closing Z or z
        $paths = preg_split('/[Zz]/', $path->getD());
        // filter out empty values
        $paths = array_filter($paths);
        $attributes = $path->getAttributes();
        $split = collect();
        
        // create a SvgPath for each path
        foreach($paths as $path)
        {
            $tag = 'path';
            // create a DOMNode
            $dom = new DOMDocument();
            $svg = new SvgPath($dom->createElement($tag));
            // overwrite the d attribute
            $attributes['d'] = trim($path);
            //$node->setAttribute('d', trim($path));
            // add the other attributes
            $svg->setAttributes($attributes);

            $split->push($svg);
        }
        return $split;
    }

    /*
    private static function filter($svgPaths, ?int $index = null, ?array $indeces = null, ?string $d = null, ?array $ds = null){
        if($d){
            $svgPaths = $svgPaths->filter(function(SvgPath $svgPath) use($d) {
                return $svgPath->getD() !== $d;
            });
        }
        if($ds){
            $svgPaths = $svgPaths->filter(function(SvgPath $svgPath) use($ds) {
                return !in_array($svgPath->getD(), $ds);
            });
        }

        // path index
        if($index){
            $svgPaths = $svgPaths->filter(function(SvgPath $svgPath, $key) use($path) {
                return $key !== $path;
            });
        }
        if($indeces){
            $svgPaths = $svgPaths->filter(function(SvgPath $svgPath, $key) use($indeces) {
                return !in_array($key, $indeces);
            });
        }

        return $svgPaths;
    }
    */

}