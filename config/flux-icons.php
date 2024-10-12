<?php

use FluxIcons\Helpers\FluxIconHelper;

return [
    'tabler' => [
        'vendor_name' => 'Tabler',
        'package_name' => '@tabler/icons',
        'source_directories' => [
            'outline' => 'node_modules/@tabler/icons/icons/outline',
            'solid' => [ // incase the solid icons are in different directories
                '24' => 'node_modules/@tabler/icons/icons/filled',
                '20' => 'node_modules/@tabler/icons/icons/filled',
                '16' => 'node_modules/@tabler/icons/icons/filled',
            ],
        ],
        'transform_svg_path' => function ($variant, $iconName, $svgPaths) {
            if($variant == 'outline'){
                // remove the first $svgPath from the array that has a d attribute of 'M0 0h24v24H0z'
                $svgPaths = array_filter($svgPaths, function($svgPath) {
                    return isset($svgPath['d']) && $svgPath['d'] !== 'M0 0h24v24H0z';
                });
            }
            return $svgPaths;
        },
        'change_stroke_width' => function ($iconName, $defaultStrokeWidth, $svgPaths) {
            // icons that have a circular shape should have a stroke width of 2 otherwise you may see a gap in the icon
            // i.e. in case one of the paths has an a (arc) command, it's a circle
            if(array_reduce($svgPaths, function($carry, $svgPath) {
                return $carry || strpos($svgPath['d'], 'a') !== false;
            }, false)){
                return 2;
            }

            //if (in_array($iconName, ['grip-horizontal', 'grip-vertical'])) {
            //    return 2;
            //}
            return $defaultStrokeWidth;
        },
    ],
    // Add other vendors here...
];