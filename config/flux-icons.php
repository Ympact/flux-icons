<?php

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
    'default_stroke_width' => 1.5,

    /**
     * Vendors configuration
     */
    'vendors' => [
        'tabler' => [
            'vendor_name' => 'Tabler',
            'namespace' => 'tabler',
            'package_name' => '@tabler/icons',
            'baseVariant' => 'outline', 
            'variants' => [
                'outline' => [
                    'stub' => 'outline', // default stub for the icon, not necessary to specify
                    'stroke_width' => 1.5, // default stroke width for the icon, not necessary to specify
                    'size' => 24, // default size for the icon, not necessary to specify
                    'path_attributes' => [
                        'stroke-linecap' => 'round',
                        'stroke-linejoin' => 'round',
                    ],
                    'source' => 'node_modules/@tabler/icons/icons/outline',
                    // filter => [],
                ],
                'solid' => [
                    'stub' => 'solid',
                    'stroke_width' => false, // there is no stroke width for solid icons
                    'size' => 24,
                    'path_attributes' => [
                        'fill-rule' => 'evenodd',
                        'clip-rule' => 'evenodd',
                    ],
                    'source' => 'node_modules/@tabler/icons/icons/filled',
                    // filter => [],
                ],
                'mini' => [
                    'base' => 'solid',
                    'size' => 20 // default size for the icon, not necessary to specify
                ], 
                'micro' => [
                    'base' => 'solid', 
                    'size' => 16 // default size for the icon, not necessary to specify
                ],
            ],
            /*
            'path_attributes'=> [
                'outline' => [
                    'stroke-linecap' => 'round',
                    'stroke-linejoin' => 'round',
                ],
                'solid' => [
                    'fill-rule' => 'evenodd',
                    'clip-rule' => 'evenodd',
                ],
            ],
            'source_directories' => [
                'outline' => 'node_modules/@tabler/icons/icons/outline', 
            ],
            */
            'transform_svg_path' => [ Ympact\FluxIcons\Services\Vendors\Tabler::class, 'transformSvgPath' ],
            'change_stroke_width' => [ Ympact\FluxIcons\Services\Vendors\Tabler::class, 'changeStrokeWidth' ] 
        ],

        // Google Material Design Icons
        'google' => [
            'vendor_name' => 'Material Design Icons',
            'namespace' => 'material',
            'package_name' => '@material-design-icons/svg',
            'build_type' => [
                'outline' => 'solid',
                'solid' => 'solid',
            ],
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
            'build_type' => [
                'outline' => 'solid',
                'solid' => 'solid',
            ],
            'source_directories' => [
                'outline' => [
                    'dir' => 'node_modules/@fluentui/svg-icons/icons',
                    'prefix' => null,
                    'suffix' => '_24_regular',
                ],
                'solid' => [
                    'dir' => 'node_modules/@fluentui/svg-icons/icons',
                    'prefix' => null, 
                    'suffix' => [ Ympact\FluxIcons\Services\Vendors\Fluent::class, 'sourceSolidSuffix' ],
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
            'build_type' => [
                'outline' => 'outline',
                'solid' => 'solid',
            ],
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
            'build_type' => [
                'outline' => 'solid',
                'solid' => 'solid',
            ],
            'source_directories' => [
                'outline' => [
                    'dir' => 'node_modules/@mdi/svg/svg',
                    'prefix' => null,
                    'suffix' => '-outline',
                    // filter function to determine if the icon is an outline icon
                    'filter' => [ Ympact\FluxIcons\Services\Vendors\Mdi::class, 'outlineFilter' ]
                ],
                'solid' => [
                    'dir' => 'node_modules/@mdi/svg/svg',
                    'prefix' => null,
                    'suffix' => null,
                    
                    // inverse of the outline filter
                    'filter' => [ Ympact\FluxIcons\Services\Vendors\Mdi::class, 'solidFilter' ]
                ]   
            ],
            'transform_svg_path' => null, 
            'change_stroke_width' => null
        ],
        
        // Add other vendors here...

    ],
];