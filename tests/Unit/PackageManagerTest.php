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
})->throws(RuntimeException::class, "composer.lock not found");

it('throws when updating an unknown vendor package', function () {
    Config::set('flux-icons', fixtureConfig());

    PackageManager::updateVendorPackage('nonexistent-vendor');
})->throws(RuntimeException::class, 'Vendor nonexistent-vendor not found in config.');
