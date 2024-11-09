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
    'icons' => [ 'vendor-1' => [ 'home', 'arrow-left' ]],

    /**
     * Default stroke width for icons
     * Heroicons and hence Flux uses by default a stroke width of 1.5 for icons
     */
    'default_stroke_width' => 1.5,

    /**
     * Vendors configuration
     */
    'vendors' => [
        'alpha' => [
            'vendor_name' => 'Alpha',
            'namespace' => 'alpha',
            'package_name' => 'alpha/icons',
            //'baseVariant' => 'outline', // default variant for the icon, not necessary to specify
            'variants' => [
                'outline' => [
                    //'stub' => 'outline', // default stub for the icon, not necessary to specify
                    //'stroke_width' => 1.5, // default stroke width for the icon, not necessary to specify
                    //'size' => 24, // default size for the icon, not necessary to specify
                    'path_attributes' => [
                        'stroke-linecap' => 'round',
                        'data-ympact' => 'alpha',
                    ],
                    'source' => 'tests/vendor/alpha/icons/outline',
                    // filter => [],
                ],
                'solid' => [
                    //'stub' => 'solid',
                    //'stroke_width' => false, // there is no stroke width for solid icons
                    //'size' => 24,
                    'path_attributes' => [
                        'fill-rule' => 'evenodd',
                        'data-ympact' => 'alpha',
                    ],
                    'source' => 'tests/vendor/alpha/icons/filled',
                    // filter => [],
                ],
                'mini' => [
                    //'base' => 'solid',
                    //'size' => 20 // default size for the icon, not necessary to specify
                ], 
                'micro' => [
                    //'base' => 'solid', 
                    //'size' => 16 // default size for the icon, not necessary to specify
                ],
            ],
            'transform_svg_path' => [ Ympact\FluxIcons\Tests\Vendor\Alpha\Alpha::class, 'transformSvgPath' ],
            'change_stroke_width' => [ Ympact\FluxIcons\Tests\Vendor\Alpha\Alpha::class, 'changeStrokeWidth' ] 
        ],

        /**
         * testing for advanced source configuration
         * - using a function to determine the suffix for the solid icon
         */
        'beta' => [
            'vendor_name' => 'Beta',
            'namespace' => 'beta',
            'package_name' => 'beta/icons',
            'variants' => [
                'outline' => [
                    'source' => [
                        'dir' => 'tests/vendor/beta/icons',
                        'prefix' => null,
                        'suffix' => '_24_regular',
                    ],
                ],
                'solid' => [
                    'source' => [
                        'dir' => 'tests/vendor/beta/icons',
                        'prefix' => null, 
                        'suffix' => [ Ympact\FluxIcons\Tests\Vendor\Beta\Beta::class, 'sourceSolidSuffix' ],
                    ],
                ],
            ],
            'transform_svg_path' => null, 
            'change_stroke_width' => null
        ],

        /**
         * testing for * to include all icons in the directory
         */
        'gamma' => [
            'vendor_name' => 'Gamma',
            'namespace' => 'gamma',
            'package_name' => 'gamma/icons',
            'variants' => [
                'outline' => [
                    'source' => 'tests/vendor/gamma/icons/outline/*/',
                ],
                'solid' => [
                    'source' => 'tests/vendor/gamma/icons/solid/*/',
                ],
            ],
            'transform_svg_path' => null, 
            'change_stroke_width' => null
        ],

        /**
         * testing for advanced source configuration: 
         * - using suffix and prefix
         * - using filter function
         * - adjusting base for mini and micro variants
         */
        'epsilon' => [
            'vendor_name' => 'Epsilon',
            'namespace' => 'epsilon',
            'package_name' => 'epsilon/icons',
            'variants' => [
                'outline' => [
                    'source' => [
                        'dir' => 'tests/vendor/epsilon/icons',
                        'prefix' => 'icon-',
                        'suffix' => '-outline',
                    ],
                    // filter function to determine if the icon is an outline icon
                    'filter' => [ Ympact\FluxIcons\Tests\Vendor\Epsilon\Epsilon::class, 'outlineFilter' ]
                ],
                'solid' => [
                    'source' => [
                        'dir' => 'tests/vendor/epsilon/icons',
                        'prefix' => 'icon-',
                        'suffix' => null,
                    ],
                    
                    // inverse of the outline filter
                    'filter' => [ Ympact\FluxIcons\Tests\Vendor\Epsilon\Epsilon::class, 'solidFilter' ]
                ],
                'mini' => [
                    'base' => 'outline',
                ],
                'micro' => [
                    'base' => 'outline',
                ],
            ],
            'transform_svg_path' => null, 
            'change_stroke_width' => null
        ],

    ],
];