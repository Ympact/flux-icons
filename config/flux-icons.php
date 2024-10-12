<?php

use Ympact\FluxIcons\Helpers\FluxIconHelper;

return [
    'tabler' => [
        'vendor_name' => 'Tabler',
        'package_name' => '@tabler/icons',
        'source_directories' => [
            'outline' => 'node_modules/@tabler/icons/icons/outline', 
            // in case the icon has a prefix or suffix in the name such as 'icon-24' or '24-icon' to be able to determine the base name of the icon
            /*
            'outline' => [
                'dir' => 'node_modules/vendor/icons/icons/outline',
                'prefix' => null,
                'suffix' => '-24',
            ],
            */
            'solid' => 'node_modules/@tabler/icons/icons/filled',
            /*'solid' => [ // in case there are different sizes of solid icons or they have a prefix or suffix in the name
                '24' => ['dir' => 'node_modules/vendor/icons/icons/filled', 'prefix' => null, 'suffix' => '-24'],
                '20' => ['dir' => 'node_modules/vendor/icons/icons/filled', 'prefix' => null, 'suffix' => '-20'],
                '16' => ['dir' => 'node_modules/vendor/icons/icons/filled', 'prefix' => null, 'suffix' => '-16'],
            ],*/
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