<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\DataTypes\Icon;
use Ympact\FluxIcons\DataTypes\SvgPath;

/**
 * Flux Icons configuration file
 * In case you have published the configuration file, you can modify the configuration here
 * The config file is merged with the default configuration, so you only need to specify the values you want to change
 */

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
     * Vendors configuration
     */
    'vendors' => [
        'tabler' => [
            'vendor_name' => 'Tabler',
            'namespace' => 'tabler',
            'package_name' => '@tabler/icons',
            'source_directories' => [
                'outline' => 'node_modules/@tabler/icons/icons/outline', 
                'solid' => 'node_modules/@tabler/icons/icons/filled',
            ],

            'transform_svg_path' => function ($variant, $iconName, $svgPaths) {
                // remove the first $svgPath from the array that has a d attribute of 'M0 0h24v24H0z'
                $svgPaths = $svgPaths->filter(function(SvgPath $svgPath){
                    return $svgPath->getD() !== 'M0 0h24v24H0z';
                });

                return $svgPaths;
            },

            'change_stroke_width' => function ($iconName, $defaultStrokeWidth, $svgPaths) {
                // icons that have a small circular shape should have a stroke width of 2 otherwise you may see a gap in the icon when using 1.5
                // ie icons such as dots, dots-vertical, grip-horizontal, grip-vertical, etc
                return $svgPaths->filter(function(SvgPath $svgPath){
                    return strpos($svgPath->getD(), 'a1 1 0 1 0') !== false;
                })->count() > 0 ? 2 : $defaultStrokeWidth;
            },
        ],

        // Google Material Design Icons
        'google' => [
            'vendor_name' => 'Material Design Icons',
            'namespace' => 'material',
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
                    'dir' => 'node_modules/@fluentui/svg-icons/icons',
                    'prefix' => null, 
                    'suffix' => fn($size) => "_{$size}_filled",
                ],
            ],
            'transform_svg_path' => null, 
            'change_stroke_width' => null
        ],

        /*
        // Flowbite icons - requires additional configuration to work properly
        // requires support for subdirectories
        */
        'flowbite' => [
            'vendor_name' => 'Flowbite',
            'namespace' => 'flowbite',
            'package_name' => 'flowbite-icons',
            'source_directories' => [
                'outline' => 'node_modules/flowbite-icons/src/outline/*/',
                'solid' => 'node_modules/flowbite-icons/src/solid/*/',
            ],
            'transform_svg_path' => null, 
            'change_stroke_width' => null
        ],


        /*
         * MDI - requires additional configuration to work properly
         * Icons are outlines by default, but in case there is an -outline variant the normal variant is solid
         */
        'mdi' => [
            'vendor_name' => 'MDI',
            'namespace' => 'mdi',
            'package_name' => '@mdi/svg',
            'source_directories' => [
                'outline' => [
                    'dir' => 'node_modules/@mdi/svg/svg',
                    'prefix' => null,
                    'suffix' => '-outline',
                    // filter function to determine if the icon is an outline icon
                    'filter' => function($file, array &$icons = null){
                        // if the icon name ends with -outline it is an outline icon
                        $filename = pathinfo($file, PATHINFO_FILENAME);
                        if (Str::contains($filename, '-outline')) {
                            return true;
                        }
                        // if there is an -outline variant of the current icon, then the icon is solid and we return false
                        // insert -outline before .svg extension to $file and check if this file exists
                        if(File::exists(Str::of($file)->before('.svg') . '-outline.svg')){
                            // if there is an outline variant of the icon, in case $icons is passed, we add the icon to the icons array and remove $filename from it
                            if(in_array( $filename, $icons)){
                                $key = array_search($filename, $icons);
                                unset($icons[$key]);
                                $icons[] = $filename.'-outline';
                            }
                            return false;
                        };
                        return true;
                    }
                ],
                'solid' => [
                    'dir' => 'node_modules/@mdi/svg/svg',
                    'prefix' => null,
                    'suffix' => null,
                    
                    // inverse of the outline filter
                    'filter' => function($file){
                        $filename = pathinfo($file, PATHINFO_FILENAME);
                        if (Str::contains($filename,'-outline')) {
                            return false;
                        }
                        return File::exists(Str::of($file)->before('.svg') . '-outline.svg') ? true : false;
                    }
                ]   
            ],
            'transform_svg_path' => null, 
            'change_stroke_width' => null
        ],
        
        // Add other vendors here...

    ],
];