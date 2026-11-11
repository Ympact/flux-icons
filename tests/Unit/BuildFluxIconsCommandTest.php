<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Build\Builder;
use Ympact\FluxIcons\Console\BuildFluxIconsCommand;
use Ympact\FluxIcons\Events\IconsBuilt;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\Alpha;

/**
 * Exposes the protected icon list helpers so the --merge behaviour can be tested
 * without running a full build (which would require an installed npm package).
 */
function iconListHelper(): object
{
    return new class extends BuildFluxIconsCommand
    {
        /**
         * @param  string|array<int,string>|null  $icons
         * @param  array<int,string>|null  $configVendorIcons
         * @return array<int,string>
         */
        public function mergeIcons($icons, $configVendorIcons): array
        {
            return $this->mergeWithConfiguredIcons($icons, $configVendorIcons);
        }

        /**
         * @param  string|array<int,string>|null  $icons
         * @return array<int,string>
         */
        public function normalize($icons): array
        {
            return $this->normalizeIcons($icons);
        }
    };
}

it('merges icons from the option with the icons configured for the vendor', function () {
    expect(iconListHelper()->mergeIcons('confetti,confetti-off', ['home', 'star']))
        ->toBe(['confetti', 'confetti-off', 'home', 'star']);
});

it('does not repeat icons that are both requested and configured', function () {
    expect(iconListHelper()->mergeIcons('home,confetti', ['home', 'star']))
        ->toBe(['home', 'confetti', 'star']);
});

it('returns only the configured icons when no icons are requested', function () {
    expect(iconListHelper()->mergeIcons(null, ['home', 'star']))
        ->toBe(['home', 'star']);
});

it('returns only the requested icons when the vendor has no configured icons', function () {
    expect(iconListHelper()->mergeIcons('home,star', null))
        ->toBe(['home', 'star']);
});

it('accepts an array of icons as well as a comma separated list', function () {
    expect(iconListHelper()->mergeIcons(['home'], ['star']))
        ->toBe(['home', 'star']);
});

it('trims whitespace and drops empty entries from an icon list', function () {
    expect(iconListHelper()->normalize(' home , , star '))
        ->toBe(['home', 'star']);
});

/**
 * The icons built into a temporary application for one namespace.
 *
 * @return list<string>
 */
function builtIcons(string $root, string $namespace): array
{
    return collect(File::glob("{$root}/resources/views/flux/icon/{$namespace}/*.blade.php"))
        ->map(fn (string $file) => basename($file, '.blade.php'))
        ->sort()
        ->values()
        ->all();
}

describe('building', function () {
    beforeEach(function () {
        $fixture = base_path('tests/vendor/alpha');
        $this->root = sys_get_temp_dir().'/flux-icons-build-command-'.Str::random(8);

        File::copyDirectory($fixture, "{$this->root}/node_modules/alpha");
        File::put("{$this->root}/node_modules/alpha/package.json", '{"name": "alpha", "version": "1.0.0"}');
        writeComposerLock($this->root, ['packages' => [], 'packages-dev' => []]);

        $this->app->setBasePath($this->root);
        $this->app[Kernel::class]->registerCommand(new BuildFluxIconsCommand);
        Config::set('flux-icons', fixtureConfig());
        Event::fake([IconsBuilt::class]);
    });

    afterEach(fn () => File::deleteDirectory($this->root));

    it('builds the icons asked for into the application', function () {
        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'home,dots', '--no-interaction' => true])
            ->assertExitCode(0);

        expect(builtIcons($this->root, 'alpha'))->toBe(['dots', 'home']);
    });

    it('builds the configured icons when none are asked for', function () {
        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--no-interaction' => true])
            ->assertExitCode(0);

        expect(builtIcons($this->root, 'alpha'))->toBe(['arrow-left', 'home']);
    });

    it('adds the configured icons to the ones asked for when merging', function () {
        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'dots', '--merge' => true, '--no-interaction' => true])
            ->assertExitCode(0);

        expect(builtIcons($this->root, 'alpha'))->toBe(['arrow-left', 'dots', 'home']);
    });

    it('builds every icon with --all', function () {
        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--all' => true, '--no-interaction' => true])
            ->assertExitCode(0);

        expect(builtIcons($this->root, 'alpha'))->toBe(['arrow-left', 'dots', 'home']);
    });

    it('publishes under the key a vendor is registered under', function () {
        Config::set('flux-icons.vendors', ['house' => Alpha::class]);

        $this->artisan('flux-icons:build', ['vendor' => 'house', '--icons' => 'home', '--no-interaction' => true])
            ->assertExitCode(0);

        expect(builtIcons($this->root, 'house'))->toBe(['home']);
    });

    it('names the icons the vendor does not ship', function () {
        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'home,nope', '--no-interaction' => true])
            ->expectsOutputToContain('Icons not found: nope')
            ->assertExitCode(0);
    });

    it('fails when none of the icons asked for could be built', function () {
        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'nope', '--no-interaction' => true])
            ->expectsOutputToContain('Icons not found: nope')
            ->expectsOutputToContain('No icons were built for vendor: alpha')
            ->doesntExpectOutputToContain('Icons built successfully')
            ->assertExitCode(1);
    });

    it('fails with the reason when the package is not where the builder looks', function () {
        // an npm workspace can hoist the package out of the application's own node_modules
        $this->app->bind(Builder::class, fn () => new Builder("{$this->root}/elsewhere", "{$this->root}/resources/views/flux/icon"));

        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'home', '--no-interaction' => true])
            ->expectsOutputToContain('The npm package alpha is not installed')
            ->assertExitCode(1);
    });

    it('fails for a vendor that is not registered', function () {
        $this->artisan('flux-icons:build', ['vendor' => 'nope', '--no-interaction' => true])
            ->expectsOutputToContain("Vendor configuration for 'nope' not found.")
            ->assertExitCode(1);
    });

    it('refuses --icons together with --all', function () {
        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'home', '--all' => true, '--no-interaction' => true])
            ->assertExitCode(1);

        expect(builtIcons($this->root, 'alpha'))->toBe([]);
    });
});
