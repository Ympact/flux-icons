<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Ympact\FluxIcons\Build\Builder;
use Ympact\FluxIcons\Events\IconsBuilt;
use Ympact\FluxIcons\Services\PackageManager;
use Ympact\FluxIcons\Support\VendorRegistry;

/*
 * Pins the components the build writes for the fixture vendors, so every change in output
 * shows as a snapshot diff. After an intended change, run `vendor/bin/pest --update-snapshots`
 * and say in the commit why the output moved.
 */

const SNAPSHOT_VENDORS = ['alpha', 'beta', 'epsilon'];

beforeEach(function () {
    Config::set('flux-icons', fixtureConfig());
    Event::fake([IconsBuilt::class]);

    $this->builder = new Builder(base_path('tests/vendor'));
    $this->vendors = VendorRegistry::fromConfig();
});

afterEach(function () {
    foreach (SNAPSHOT_VENDORS as $namespace) {
        File::deleteDirectory(resource_path("views/flux/icon/{$namespace}"));
    }
});

/**
 * Every component written for a namespace, in file order, without what differs between runs:
 * the build time, the installed Flux version, and `@blaze`, which depends on that version.
 */
function builtComponents(string $namespace): string
{
    return collect(File::files(resource_path("views/flux/icon/{$namespace}")))
        ->sortBy(fn (SplFileInfo $file) => $file->getFilename())
        ->map(fn (SplFileInfo $file) => "=== {$file->getFilename()} ===\n".(string) file_get_contents($file->getPathname()))
        ->map(fn (string $component) => (string) preg_replace(
            ['/^(\s*Flux:).*$/m', '/^(\s*Built:).*$/m', '/^@blaze$/m'],
            ['$1 <flux version>', '$1 <build date>', ''],
            $component
        ))
        ->implode("\n");
}

it('writes the components it has always written', function (string $vendor) {
    $this->builder->build($this->vendors->get($vendor));

    expect(builtComponents($vendor))->toMatchSnapshot();
})->with(SNAPSHOT_VENDORS);

it('marks components for Blaze only on a Flux version that has it', function () {
    $this->builder->build($this->vendors->get('alpha'), ['home']);

    $component = (string) file_get_contents(resource_path('views/flux/icon/alpha/home.blade.php'));

    expect(str_contains($component, "\n@blaze\n"))->toBe(PackageManager::fluxVersionAtLeast('2.2.6'));
});
