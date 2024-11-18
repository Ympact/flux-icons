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
        /**
         * Tabler
         */
        'tabler' => [
            'vendor' => 'Tabler',
            'namespace' => 'tabler',
            'package' => '@tabler/icons',
            'variants' => [
                'outline' => [
                    //'template' => 'outline', // default stub for the icon, not necessary to specify
                    //'stroke_width' => 1.5, // default stroke width for the icon, not necessary to specify
                    //'size' => 24, // default size for the icon, not necessary to specify
                    'attributes' => [
                        'stroke-linecap' => 'round',
                        'stroke-linejoin' => 'round',
                    ],
                    'source' => 'node_modules/@tabler/icons/icons/outline',
                    // filter => [],
                ],
                'solid' => [
                    //'template' => 'solid',
                    //'fallback' => 'default', // default is baseVariant. 'variant'|false => in case false the icon will not be published at all
                    //'stroke_width' => false, // there is no stroke width for solid icons
                    //'size' => 24,
                    'attributes' => [
                        'fill-rule' => 'evenodd',
                        'clip-rule' => 'evenodd',
                    ],
                    'source' => 'node_modules/@tabler/icons/icons/filled',
                    // filter => [],
                ],
                /*
                'mini' => [
                    'base' => 'solid',
                    //'size' => 20 // default size for the icon, not necessary to specify
                ], 
                'micro' => [
                    'base' => 'solid', 
                    //'size' => 16 // default size for the icon, not necessary to specify
                ],
                */
            ],
            // adjust individual icons
            'attributes' => [ Ympact\FluxIcons\Services\Vendors\Tabler::class, 'attributes' ],
            'transform' => [ Ympact\FluxIcons\Services\Vendors\Tabler::class, 'transform' ],
            'stroke_width' => [ Ympact\FluxIcons\Services\Vendors\Tabler::class, 'strokeWidth' ] 
        ],

        /**
         * Google Material Design Icons
         *
         */
        'google' => [
            'vendor' => 'Material Design Icons',
            'namespace' => 'material',
            'package' => '@material-design-icons/svg',
            'variants' => [
                'outline' => [
                    'template' => 'solid',
                    'attributes' => [
                        'stroke-linecap' => 'round',
                        'stroke-linejoin' => 'round',
                    ],
                    'source' => 'node_modules/@material-design-icons/svg/outlined',
                ],
                'solid' => [
                    //'stroke_width' => false, // there is no stroke width for solid icons
                    //'size' => 24,
                    //'attributes' => [],
                    'source' => 'node_modules/@material-design-icons/svg/filled',
                ],
                /*
                'mini' => [
                    'base' => 'solid',
                ], 
                'micro' => [
                    'base' => 'solid', 
                ],*/
            ],
            'attributes' => null,
            'transform' => null, 
            'stroke_width' => null
        ],

        /**
         * Fluent ui 
         */
        'fluent' => [
            'vendor' => 'Fluent UI',
            'namespace' => 'fluent',
            'package' => '@fluentui/svg-icons',
            'variants' => [
                'outline' => [
                    'template' => 'solid',
                    'source' => [
                        'dir' => 'node_modules/@fluentui/svg-icons/icons',
                        'prefix' => null,
                        'suffix' => '_24_regular',
                    ],
                ],
                'solid' => [
                    'source' => [
                        'dir' => 'node_modules/@fluentui/svg-icons/icons',
                        'prefix' => null, 
                        'suffix' => [ Ympact\FluxIcons\Services\Vendors\Fluent::class, 'sourceSolidSuffix' ],
                    ],
                ],
            ],
            'attributes' => null,
            'transform' => [ Ympact\FluxIcons\Services\Vendors\Fluent::class, 'transform' ], 
            'stroke_width' => null
        ],

        /**
         * Flowbite icons - requires additional configuration to work properly
         * requires support for subdirectories
         */
        'flowbite' => [
            'vendor' => 'Flowbite',
            'namespace' => 'flowbite',
            'package' => 'flowbite-icons',
            'variants' => [
                'outline' => [
                    'source' => 'node_modules/flowbite-icons/src/outline/*/'
                ],
                'solid' => [
                    'source' => 'node_modules/flowbite-icons/src/solid/*/'
                ],
            ],
            'attributes' => null,
            'transform' => null, 
            'stroke_width' => null
        ],


        /**
         * MDI - requires additional configuration to work properly
         * Icons are outlines by default, but in case there is an -outline variant the normal variant is solid
         */
        'mdi' => [
            'vendor' => 'MDI',
            'namespace' => 'mdi',
            'package' => '@mdi/svg',
            'variants' => [
                'outline' => [
                    'template' => 'solid',
                    'source' => [
                        'dir' => 'node_modules/@mdi/svg/svg',
                        'prefix' => null,
                        'suffix' => '-outline',
                        // filter function to determine if the icon is an outline icon
                        'filter' => [ Ympact\FluxIcons\Services\Vendors\Mdi::class, 'outlineFilter' ]
                    ],
                ],
                'solid' => [
                    'source' => [
                        'dir' => 'node_modules/@mdi/svg/svg',
                        'prefix' => null,
                        'suffix' => null,
                        
                        // inverse of the outline filter
                        'filter' => [ Ympact\FluxIcons\Services\Vendors\Mdi::class, 'solidFilter' ]
                    ],
                ],
            ],
            'attributes' => null,
            'transform' => null, 
            'stroke_width' => null
        ],
        
        // Add other vendors here...

    ],
];