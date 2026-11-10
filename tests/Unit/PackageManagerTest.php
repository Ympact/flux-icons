<?php

use Illuminate\Support\Facades\Config;
use Ympact\FluxIcons\Services\PackageManager;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/flux-icons-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->app->setBasePath($this->tempDir);
});

afterEach(function () {
    removeDirectory($this->tempDir);
});

it('reads the flux version from composer lock', function () {
    writeComposerLock($this->tempDir, [
        'packages' => [
            ['name' => 'livewire/flux', 'version' => '2.1.0'],
        ],
        'packages-dev' => [],
    ]);

    expect(PackageManager::fluxVersion())->toBe('2.1.0');
});

it('returns null when flux is not present in composer lock', function () {
    writeComposerLock($this->tempDir, [
        'packages' => [
            ['name' => 'some/other-package', 'version' => '1.0.0'],
        ],
        'packages-dev' => [],
    ]);

    expect(PackageManager::fluxVersion())->toBeNull();
});

it('throws when composer lock is missing', function () {
    PackageManager::fluxVersion();
})->throws(RuntimeException::class, 'composer.lock not found');

it('compares flux versions numerically instead of as strings', function () {
    writeComposerLock($this->tempDir, [
        'packages' => [
            ['name' => 'livewire/flux', 'version' => 'v2.10.0'],
        ],
        'packages-dev' => [],
    ]);

    // a plain string comparison would place 2.10.0 below 2.2.6
    expect(PackageManager::fluxVersionAtLeast('2.2.6'))->toBeTrue();
});

it('reports flux versions below the requested version', function () {
    writeComposerLock($this->tempDir, [
        'packages' => [
            ['name' => 'livewire/flux', 'version' => 'v2.2.5'],
        ],
        'packages-dev' => [],
    ]);

    expect(PackageManager::fluxVersionAtLeast('2.2.6'))->toBeFalse();
});

it('treats development flux versions as up to date', function (string $version) {
    writeComposerLock($this->tempDir, [
        'packages' => [
            ['name' => 'livewire/flux', 'version' => $version],
        ],
        'packages-dev' => [],
    ]);

    expect(PackageManager::fluxVersionAtLeast('2.2.6'))->toBeTrue();
})->with([
    'dev-main',
    'dev-master',
    // version_compare ranks the "x" below any number, so this must not reach it
    '2.x-dev',
    'dev-feature/blaze',
]);

it('compares pre-release flux versions against the release they precede', function (string $version, bool $expected) {
    writeComposerLock($this->tempDir, [
        'packages' => [
            ['name' => 'livewire/flux', 'version' => $version],
        ],
        'packages-dev' => [],
    ]);

    expect(PackageManager::fluxVersionAtLeast('2.2.6'))->toBe($expected);
})->with([
    ['2.13.1-beta.1', true],
    ['2.0.0-beta.1', false],
]);

it('reports flux as outdated when it is not installed', function () {
    writeComposerLock($this->tempDir, [
        'packages' => [],
        'packages-dev' => [],
    ]);

    expect(PackageManager::fluxVersionAtLeast('2.2.6'))->toBeFalse();
});

it('throws when updating an unknown vendor package', function () {
    Config::set('flux-icons', fixtureConfig());

    PackageManager::updateVendorPackage('nonexistent-vendor');
})->throws(RuntimeException::class, 'Vendor nonexistent-vendor not found in config.');
