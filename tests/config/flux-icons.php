<?php

use Ympact\FluxIcons\Tests\Fixtures\Vendors\Alpha;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\Beta;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\Epsilon;

/**
 * The package config as the tests use it: the fixture vendors under tests/vendor.
 */
return [
    'request_npm_dev' => false,

    'icons' => ['alpha' => ['home', 'arrow-left']],

    'vendors' => [
        'alpha' => Alpha::class,
        'beta' => Beta::class,
        'epsilon' => Epsilon::class,
    ],
];
