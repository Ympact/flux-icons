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
            'transform_svg_path' => [ Ympact\FluxIcons\Services\Vendors\Tabler::class, 'transformSvgPath' ],
            'change_stroke_width' => [ Ympact\FluxIcons\Services\Vendors\Tabler::class, 'changeStrokeWidth' ] 
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