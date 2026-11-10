<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Ympact\FluxIcons\Services\IconBuilder;

/*
 * Builds through the shipped MDI config, pointed at a fixture holding one icon that is its
 * own outline (abacus) and one with an outline sibling (account, account-outline).
 */
beforeEach(function () {
    Config::set('flux-icons', require dirname(__DIR__, 2).'/config/flux-icons.php');
    Config::set('flux-icons.request_npm_dev', false);

    foreach (['outline', 'solid'] as $variant) {
        Config::set("flux-icons.vendors.mdi.variants.{$variant}.source.dir", 'tests/vendor/mdi/svg');
    }

    $this->outputDir = resource_path('views/flux/icon/mdi');
});

afterEach(fn () => File::deleteDirectory($this->outputDir));

it('builds an mdi icon that is its own outline', function () {
    (new IconBuilder('mdi', ['abacus']))->buildIcons();

    expect("{$this->outputDir}/abacus.blade.php")->toBeFile();
});

it('draws every variant of such an icon from its own file', function () {
    (new IconBuilder('mdi', ['abacus']))->buildIcons();

    $component = (string) file_get_contents("{$this->outputDir}/abacus.blade.php");

    expect(substr_count($component, 'M1 1h2v2H1z'))->toBe(4);
});

it('still draws an mdi icon with an outline sibling from both files', function () {
    (new IconBuilder('mdi', ['account']))->buildIcons();

    $component = (string) file_get_contents("{$this->outputDir}/account.blade.php");

    expect(substr_count($component, 'M3 3h6v6H3z'))->toBe(1)
        ->and(substr_count($component, 'M2 2h4v4H2z'))->toBe(3);
});
