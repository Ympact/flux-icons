<?php

/**
 * Flux Icons configuration file
 * In case you have published the configuration file, you can modify the configuration here
 * The config file is merged with the default configuration, so you only need to specify the values you want to change
 */

return [

    /**
     * Request to restart npm dev after building icons
     */
    'request_npm_dev' => true, 

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
         * Bootstrap Icons
         * @see https://icons.getbootstrap.com/
         * Icons are available in solid and outline variants
         */
        'bootstrap' => [
            'vendor_name' => 'Bootstrap',
            'namespace' => 'bootstrap',
            'package' => 'bootstrap-icons',
            'variants' => [
                'outline' => [
                    'template' => 'solid',
                    'source' => [
                        'dir' => 'node_modules/bootstrap-icons/icons',
                        'prefix' => null,
                        'suffix' => null,
                        // since solid and outline icons are in the same directory,
                        // use a filter function to determine if the icon is an outline icon
                        'filter' => [ Ympact\FluxIcons\Services\Vendors\Bootstrap::class, 'outlineFilter' ]
                    ],
                ],
                'solid' => [
                    'source' => [
                        'dir' => 'node_modules/bootstrap-icons/icons',
                        'prefix' => null,
                        'suffix' => '-fill',
                        // since solid and outline icons are in the same directory,
                        // use a filter function to determine if the icon is a solid icon
                        'filter' => [ Ympact\FluxIcons\Services\Vendors\Bootstrap::class, 'solidFilter' ]
                    ],
                ],
            ],
        ],

        /**
         * VSCode Codicons
         * @see https://microsoft.github.io/vscode-codicons/dist/codicon.html
         * Codicons has only outline icons, so there is no difference between outline and solid variant
         */
        'codicons' => [
            'vendor_name' => 'VSCode Codicons',
            'namespace' => 'codicons',
            'package' => '@vscode/codicons',
            'variants' => [
                'outline' => [
                    'template' => 'solid',
                    'source' => 'node_modules/@vscode/codicons/src/icons',
                ],
                // codicons doesnt have solid icons, so we just use the outline icons
                'solid' => [
                    'source' => 'node_modules/@vscode/codicons/src/icons',
                ],
            ],
        ],

        /**
         * Flowbite icons 
         * @see https://flowbite.com/icons/
         * icons are available in outline and solid variants within various subdirectories
         */
        'flowbite' => [
            'vendor_name' => 'Flowbite',
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
        ],
        
        /**
         * Fluent ui 
         * @see https://developer.microsoft.com/en-us/fluentui#/styles/web/icons
         */
        'fluent' => [
            'vendor_name' => 'Fluent UI',
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
            'transform' => [ Ympact\FluxIcons\Services\Vendors\Fluent::class, 'transform' ], 
        ],

        /**
         * Health Icons
         * @see https://healthicons.org/
         * Health Icons has only solid icons, so there is no difference between outline and solid variant
         * Some icons have a 48 and 24px variant:
         *   48px is default size and is used for outline and solid variants. 
         *   In case 24px exists, it is used for mini and micro variants.
         */
        'healthicons' => [
            'vendor_name' => 'Healthicons',
            'namespace' => 'healthicons',
            'package' => 'healthicons',
            'variants' => [
                'outline' => [
                    'template' => 'solid',
                    'source' => 'node_modules/healthicons/public/icons/svg/outline/*/',
                ],
                'solid' => [
                    'source' => 'node_modules/healthicons/public/icons/svg/filled/*/'
                ],
                'mini' => [
                    'base' => 'solid',
                    'fallback' => 'solid', 
                    'source' => 'node_modules/healthicons/public/icons/svg/filled-24px/*/'
                ],
                'micro' => [
                    'base' => 'solid',
                    'fallback' => 'solid', 
                    'source' => 'node_modules/healthicons/public/icons/svg/filled-24px/*/'
                ],
            ],
            'icon_name' => [ Ympact\FluxIcons\Services\Vendors\Healthicons::class, 'name' ]
        ],

        /**
         * Lucide Icons
         * @see https://lucide.dev/
         * Lucide has only outline icons, so there is no difference between outline and solid variant
         */
        'lucide' => [
            'vendor_name' => 'Lucide',
            'namespace' => 'lucide',
            'package' => 'lucide-static',
            'variants' => [
                'outline' => [
                    'template' => 'outline',
                    'source' => 'node_modules/lucide-static/icons',
                ],
                // lucide doesnt have solid icons, so we just use the outline icons
                'solid' => [
                    'template' => 'outline',
                    'source' => 'node_modules/lucide-static/icons',
                ],
            ],
        ],

        /**
         * Google Material Design Icons
         * @see https://fonts.google.com/icons
         */
        'material-icons' => [
            'vendor_name' => 'Material Design Icons',
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
                    'stroke_width' => false, // there is no stroke width for solid icons
                    'source' => 'node_modules/@material-design-icons/svg/filled',
                ],
            ],
        ],

        /**
         * Material Design Icons
         * @see https://fonts.google.com/icons?icon.set=Material+Symbols
         */
        'material-symbols' => [
            'vendor_name' => 'Material Symbols 300',
            'namespace' => 'material-symbols',
            'package' => '@material-symbols/svg-300',
            'variants' => [
                'outline' => [
                    'template' => 'solid',
                    'source' => [
                        'dir' => 'node_modules/@material-symbols/svg-300/outlined',
                        'filter' => [ Ympact\FluxIcons\Services\Vendors\MaterialSymbols::class, 'outlineFilter' ]
                    ],
                ],
                'solid' => [
                    'source' => [
                        'dir' => 'node_modules/@material-symbols/svg-300/outlined',
                        'suffix' => '-fill',
                        'filter' => [ Ympact\FluxIcons\Services\Vendors\MaterialSymbols::class, 'solidFilter' ]
                    ]
                ], 
            ],  
        ], 

        /**
         * MDI 
         * @see https://materialdesignicons.com/
         * Icons are outlines by default, but in case there is an -outline variant the normal variant is solid
         */
        'mdi' => [
            'vendor_name' => 'MDI',
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
        ],

        /**
         * Tabler
         * @see https://tablericons.com/
         */
        'tabler' => [
            'vendor_name' => 'Tabler',
            'namespace' => 'tabler',
            'package' => '@tabler/icons',
            'baseVariant' => 'outline', 
            'variants' => [
                'outline' => [
                    'template' => 'outline', // default stub for the icon, not necessary to specify
                    'stroke_width' => 1.5, // default stroke width for the icon, not necessary to specify
                    'size' => 24, // default size for the icon, not necessary to specify
                    'attributes' => [
                        'stroke-linecap' => 'round',
                        'stroke-linejoin' => 'round',
                    ],
                    'source' => 'node_modules/@tabler/icons/icons/outline',
                    // filter => [],
                ],
                'solid' => [
                    'template' => 'solid',
                    'fallback' => 'default', // default is baseVariant. 'variant'|false => in case false the icon will not be published at all
                    'stroke_width' => false, // there is no stroke width for solid icons
                    'size' => 24,
                    'attributes' => [
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
            // adjust individual icons
            //'name' => [ Ympact\FluxIcons\Services\Vendors\Tabler::class, 'name' ],
            'attributes' => [ Ympact\FluxIcons\Services\Vendors\Tabler::class, 'attributes' ],
            'transform' => [ Ympact\FluxIcons\Services\Vendors\Tabler::class, 'transform' ],
            'stroke_width' => [ Ympact\FluxIcons\Services\Vendors\Tabler::class, 'strokeWidth' ] 
        ],
    ],
];