<?php

use Ympact\FluxIcons\DataTypes\Icon;
use Ympact\FluxIcons\DataTypes\SvgPath;

return [

     /**
      * Default icons to be used in the project
      * Listing icons here will make them auto buildable and updatable through flux-icons:build and flux-icons:update commands
      * null or array ['vendor' => ['icon-name', ...] ] 
      */  
    'icons' => null,

    /**
     * Default stroke width for icons
     * Heroicons and hence Flux uses by default a stroke width of 1.5 for icons
     */
    'default_stroke_wdith' => 1.5,

    /**
     * Default sizes for solid icons
     * Heroicons and hence Flux uses by default 24, 20, and 16 sizes for solid icons
     * Adjusting this is not yet properly implemented in this package
     */
    'solid_sizes' => [24, 20, 16],

    /**
     * Vendors configuration
     */
    'vendors' => [
        'tabler' => [
            'vendor_name' => 'Tabler',
            'namespace' => 'tabler',
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
            /**
             * @param string $variant (solid, outline)
             * @param string $iconName
             * @param collection<SvgPath> $svgPaths
             */
            'transform_svg_path' => function ($variant, $iconName, $svgPaths) {
                // remove the first $svgPath from the array that has a d attribute of 'M0 0h24v24H0z'
                $svgPaths = $svgPaths->filter(function($svgPath){
                    return $svgPath->getD() !== 'M0 0h24v24H0z';
                });

                return $svgPaths;
            },

            /**
             * @param string $iconName
             * @param float $defaultStrokeWidth
             * @param collection<SvgPath> $svgPaths
             */
            'change_stroke_width' => function ($iconName, $defaultStrokeWidth, $svgPaths) {
                // icons that have a small circular shape should have a stroke width of 2 otherwise you may see a gap in the icon when using 1.5
                // ie icons such as dots, dots-vertical, grip-horizontal, grip-vertical, etc
                return $svgPaths->filter(function($svgPath){
                    return strpos($svgPath->getD(), 'a1 1 0 1 0') !== false;
                })->count() > 0 ? 2 : $defaultStrokeWidth;
            },
        ],

        // Google Material Design Icons
        'google' => [
            'vendor_name' => 'Material Design Icons',
            'namespace' => 'google',
            'package_name' => '@material-design-icons/svg',
            'source_directories' => [
                'outline' => 'node_modules/@material-design-icons/svg/outlined',
                'solid' => 'node_modules/@material-design-icons/svg/filled',
            ],
            'transform_svg_path' => null, 
            'change_stroke_width' => null
        ],

        // Fluent ui 
        'fluent' => [
            'vendor_name' => 'Fluent UI',
            'namespace' => 'fluent',
            'package_name' => '@fluentui/svg-icons',
            'source_directories' => [
                'outline' => [
                    'dir' => 'node_modules/@fluentui/svg-icons/icons',
                    'prefix' => null,
                    'suffix' => '_24_regular',
                ],
                'solid' => [
                    '24' => ['dir' => 'node_modules/@fluentui/svg-icons/icons', 'prefix' => null, 'suffix' => '_24_filled'],
                    '20' => ['dir' => 'node_modules/@fluentui/svg-icons/icons', 'prefix' => null, 'suffix' => '_20_filled'],
                    '16' => ['dir' => 'node_modules/@fluentui/svg-icons/icons', 'prefix' => null, 'suffix' => '_16_filled'],
                ],
            ],
            'transform_svg_path' => null, 
            'change_stroke_width' => null
        ],

        /*
        // Flowbite icons - requires additional configuration to work properly
        // requires support for subdirectories
        'flowbite' => [
            'vendor_name' => 'Flowbite',
            'namespace' => 'fluent',
            'package_name' => 'flowbite/icons',
            'source_directories' => [
                'outline' => 'node_modules/flowbite-icons/src/outline/ /',
                'solid' => 'node_modules/flowbite-icons/src/solid/ /',
            ],
            'transform_svg_path' => null, 
            'change_stroke_width' => null
        ],
        */

        /*
         * MDI - requires additional configuration to work properly
         * Icons are outlines by default, but in case there is an -outline variant the normal variant is solid
        'mdi' => [
            'vendor_name' => 'MDI',
            'namespace' => 'mdi',
            'package_name' => '@mdi/svg',
            'source_directories' => [
                'outline' => [
                    'dir' => 'node_modules/@mdi/svg',
                    'prefix' => null,
                    'suffix' => '-outline',
                ],
                'solid' => 'node_modules/@mdi/svg',
            ],
            'transform_svg_path' => null, 
            'change_stroke_width' => null
        ],
        */
        // Add other vendors here...

    ],
];