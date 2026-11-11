<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Build\Builder;
use Ympact\FluxIcons\Console\UpdateFluxIconsCommand;
use Ympact\FluxIcons\Events\IconsBuilt;
use Ympact\FluxIcons\Services\NpmManager;
use Ympact\FluxIcons\Support\VendorRegistry;
use Ympact\FluxIcons\Vendors\MaterialIcons;
use Ympact\FluxIcons\Vendors\Tabler;

/**
 * Asks which built directories an update would touch, which needs no npm.
 */
class UpdateCommandProbe extends UpdateFluxIconsCommand
{
    /**
     * @param  list<string>  $directories
     * @return list<string>
     */
    public function requested(array $directories, string $option): array
    {
        $registry = new VendorRegistry(['material-icons' => MaterialIcons::class, 'tabler' => Tabler::class]);

        return $this->onlyRequested($registry, $directories, $option);
    }
}

// material-icons publishes to material: the key and the namespace differ
it('updates a vendor named by its key or by the directory its icons were built into', function (string $option, array $expected) {
    expect((new UpdateCommandProbe)->requested(['flux-icons', 'material', 'tabler'], $option))->toBe($expected);
})->with([
    'by key' => ['material-icons', ['material']],
    'by directory' => ['material', ['material']],
    'several, spaced' => ['tabler, material-icons', ['material', 'tabler']],
]);

it('stops when none of the vendors named has built icons', function () {
    $root = sys_get_temp_dir().'/flux-icons-update-'.Str::random(8);
    File::ensureDirectoryExists("{$root}/resources/views/flux/icon/tabler");
    $this->app->setBasePath($root);
    $this->app[Kernel::class]->registerCommand(new UpdateFluxIconsCommand);
    Config::set('flux-icons.vendors', ['tabler' => Tabler::class]);

    try {
        $this->artisan('flux-icons:update', ['--vendor' => 'nope'])
            ->expectsOutputToContain('No built icons found for: nope')
            ->expectsOutputToContain('None of the requested vendors have built icons')
            ->assertExitCode(1);
    } finally {
        File::deleteDirectory($root);
    }
});

describe('updating built icons', function () {
    beforeEach(function () {
        $this->root = sys_get_temp_dir().'/flux-icons-update-'.Str::random(8);

        File::copyDirectory(base_path('tests/vendor/alpha'), "{$this->root}/node_modules/alpha");
        File::put("{$this->root}/node_modules/alpha/package.json", '{"name": "alpha", "version": "1.0.0"}');
        File::ensureDirectoryExists("{$this->root}/resources/views/flux/icon/alpha");
        File::put("{$this->root}/resources/views/flux/icon/alpha/home.blade.php", '');
        writeComposerLock($this->root, ['packages' => [], 'packages-dev' => []]);

        $this->app->setBasePath($this->root);
        Config::set('flux-icons', fixtureConfig());
        Event::fake([IconsBuilt::class]);
        $this->app[Kernel::class]->registerCommand(new UpdateFluxIconsCommand);
        $this->app->bind(NpmManager::class, fn () => new NpmManager(binary: fakeNpm($this->root)));
    });

    afterEach(fn () => File::deleteDirectory($this->root));

    it('updates the package of the icons that were built, and rebuilds them', function () {
        $this->artisan('flux-icons:update')->assertExitCode(0);

        expect(fakeNpmCalls($this->root))->toBe(["{$this->root} update alpha --silent"])
            ->and((string) file_get_contents("{$this->root}/resources/views/flux/icon/alpha/home.blade.php"))->toContain('<flux:icon.alpha.home />');
    });

    it('rebuilds with the builder the application binds, and fails when that cannot build', function () {
        // an npm workspace can hoist the package out of the application's own node_modules
        $this->app->bind(Builder::class, fn () => new Builder("{$this->root}/elsewhere", "{$this->root}/resources/views/flux/icon"));

        $this->artisan('flux-icons:update')
            ->expectsOutputToContain("The npm package alpha is not installed in {$this->root}/elsewhere")
            ->expectsOutputToContain('The update failed for: alpha.')
            ->assertExitCode(1);
    });

    it('fails when a package could not be updated, saying what npm said', function () {
        $this->app->bind(NpmManager::class, fn () => new NpmManager(binary: fakeNpm($this->root, exitCode: 1, stderr: 'E404 Not Found')));

        $this->artisan('flux-icons:update')
            ->expectsOutputToContain('npm could not update alpha: E404 Not Found')
            ->expectsOutputToContain('The update failed for: alpha.')
            ->assertExitCode(1);
    });
});
