<?php

use Illuminate\Support\Collection;
use Ympact\FluxIcons\Services\IconManager;

beforeEach(function () {
    $this->iconDir = $this->app->resourcePath('views/flux/icon');
});

afterEach(function () {
    removeDirectory($this->iconDir);
});

it('returns no vendors when no icon directories exist', function () {
    $vendors = IconManager::currentVendors();

    expect($vendors)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(0);
});

it('returns vendor directory names from built icons', function () {
    mkdir($this->iconDir.'/heroicons', 0755, true);
    mkdir($this->iconDir.'/tabler', 0755, true);

    expect(IconManager::currentVendors()->toArray())
        ->toContain('heroicons', 'tabler');
});

it('returns an empty icon list for an empty vendor directory', function () {
    mkdir($this->iconDir.'/heroicons', 0755, true);

    $icons = IconManager::installedIcons(collect(['heroicons']));

    expect($icons->toArray())
        ->toHaveKey('heroicons')
        ->and($icons['heroicons'])->toHaveCount(0);
});

it('lists installed blade icon names for a vendor', function () {
    $dir = $this->iconDir.'/heroicons';
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/home.blade.php', '');
    file_put_contents($dir.'/arrow-left.blade.php', '');

    $icons = IconManager::installedIcons(collect(['heroicons']));

    expect($icons['heroicons'])->toMatchSortedValues(['arrow-left', 'home']);
});

it('groups installed icon names by vendor', function () {
    mkdir($this->iconDir.'/heroicons', 0755, true);
    mkdir($this->iconDir.'/tabler', 0755, true);
    file_put_contents($this->iconDir.'/heroicons/home.blade.php', '');
    file_put_contents($this->iconDir.'/tabler/star.blade.php', '');

    $icons = IconManager::installedIcons(collect(['heroicons', 'tabler']));

    expect($icons->toArray())
        ->toHaveKey('heroicons')
        ->toHaveKey('tabler')
        ->and($icons['heroicons']->toArray())->toContain('home')
        ->and($icons['tabler']->toArray())->toContain('star');
});

it('discovers vendors automatically when none are provided', function () {
    $dir = $this->iconDir.'/alpha';
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/icon1.blade.php', '');

    expect(IconManager::installedIcons()->toArray())->toHaveKey('alpha');
});
