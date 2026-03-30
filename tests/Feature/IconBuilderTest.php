<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Ympact\FluxIcons\Services\IconBuilder;

beforeEach(function () {
    Config::set('flux-icons', fixtureConfig());

    $this->iconBuilder = new IconBuilder;
});

it('exposes the configured vendors', function () {
    $vendors = IconBuilder::getAvailableVendors();

    expect($vendors)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(4);

    expect(array_keys($vendors->toArray()))->toMatchSortedValues(['alpha', 'beta', 'gamma', 'epsilon']);
});

it('collects available icons from the configured source', function () {
    $this->iconBuilder->setVendor('alpha');

    File::shouldReceive('glob')
        ->once()
        ->withArgs(function ($arg) {
            $path = (string) $arg;

            return str_contains($path, 'tests/vendor/alpha/icons') && str_ends_with($path, 'outline/*.svg');
        })
        ->andReturn(['icon1.svg', 'icon2.svg']);

    $icons = $this->iconBuilder->getAvailableIcons('outline');

    expect($icons)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(2)
        ->and($icons->toArray())->toBe(['icon1.svg', 'icon2.svg']);
});
