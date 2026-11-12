<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Services\DeclaredIcons;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/flux-icons-declared-'.Str::random(8);
    File::ensureDirectoryExists($this->root);
    $this->app->setBasePath($this->root);
});

afterEach(fn () => File::deleteDirectory($this->root));

it('reads the icons each installed package declares, by namespace', function () {
    writeInstalledPackages($this->root, [
        'acme/shop' => ['tabler' => ['map-pin', 'key', 'map-pin'], 'mdi' => ['cart']],
        'acme/blog' => ['tabler' => ['pencil']],
    ]);

    expect((new DeclaredIcons)->all())->toBe([
        'acme/blog' => ['tabler' => ['pencil']],
        'acme/shop' => ['tabler' => ['map-pin', 'key'], 'mdi' => ['cart']],
    ]);
});

it('gathers one namespace across the packages that declare it', function () {
    writeInstalledPackages($this->root, [
        'acme/shop' => ['tabler' => ['map-pin', 'key']],
        'acme/blog' => ['tabler' => ['key', 'pencil'], 'mdi' => ['cart']],
    ]);

    expect((new DeclaredIcons)->forNamespace('tabler'))->toBe(['key', 'pencil', 'map-pin'])
        ->and((new DeclaredIcons)->forNamespace('lucide'))->toBe([]);
});

it('leaves out what is not an icon name, and packages that declare nothing', function () {
    writeInstalledPackages($this->root, [
        'acme/odd' => ['tabler' => ['home', 3, '', ['nested']], 'mdi' => 'cart', 7 => ['x']],
    ]);
    $installed = json_decode((string) file_get_contents("{$this->root}/vendor/composer/installed.json"), true);
    $installed['packages'][] = ['name' => 'acme/plain', 'version' => '1.0.0'];
    $installed['packages'][] = ['name' => 'acme/wrong', 'extra' => ['flux-icons' => ['icons' => 'tabler.home']]];
    file_put_contents("{$this->root}/vendor/composer/installed.json", json_encode($installed));

    expect((new DeclaredIcons)->all())->toBe(['acme/odd' => ['tabler' => ['home']]]);
});

it('reads the list composer 1 wrote', function () {
    File::ensureDirectoryExists("{$this->root}/vendor/composer");
    File::put("{$this->root}/vendor/composer/installed.json", json_encode([
        ['name' => 'acme/shop', 'extra' => ['flux-icons' => ['icons' => ['tabler' => ['key']]]]],
    ]));

    expect((new DeclaredIcons)->all())->toBe(['acme/shop' => ['tabler' => ['key']]]);
});

it('declares nothing without an installed.json', function () {
    expect((new DeclaredIcons)->all())->toBe([]);
});

it('finds composer\'s vendor directory where COMPOSER_VENDOR_DIR puts it', function () {
    writeInstalledPackages("{$this->root}/elsewhere", ['acme/shop' => ['tabler' => ['key']]]);
    putenv("COMPOSER_VENDOR_DIR={$this->root}/elsewhere/vendor");

    try {
        expect((new DeclaredIcons)->all())->toBe(['acme/shop' => ['tabler' => ['key']]]);
    } finally {
        putenv('COMPOSER_VENDOR_DIR');
    }
});

it('leaves out a declared name that could not be an icon, and trims the rest as the build does', function () {
    writeInstalledPackages($this->root, [
        'acme/odd' => ['tabler' => ['x$(touch /tmp/pwned)', 'a,b', 'pin ok', "\e[2Jclear", ' dots ', 'map-pin'], 'bad space' => ['home']],
    ]);

    expect((new DeclaredIcons)->all())->toBe(['acme/odd' => ['tabler' => ['dots', 'map-pin']]]);
});
