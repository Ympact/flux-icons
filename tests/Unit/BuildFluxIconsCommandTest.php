<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Build\Builder;
use Ympact\FluxIcons\Console\BuildFluxIconsCommand;
use Ympact\FluxIcons\Events\IconsBuilt;
use Ympact\FluxIcons\Services\NpmManager;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\Alpha;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\AlphaStyled;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\AlphaUnstyled;

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

/**
 * The <svg> markup of a built icon's outline variant.
 */
function outlineOf(string $root, string $namespace, string $icon): string
{
    $component = (string) file_get_contents("{$root}/resources/views/flux/icon/{$namespace}/{$icon}.blade.php");
    preg_match("/case \\('outline'\\): \\?>\\s*(.*?)\\s*<\\?php break; \\?>/s", $component, $outline);

    return $outline[1] ?? '';
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

    it('builds the icons packages declare along with the configured ones', function () {
        writeInstalledPackages($this->root, ['acme/shop' => ['alpha' => ['dots', 'home'], 'tabler' => ['key']]]);

        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--no-interaction' => true])
            ->assertExitCode(0);

        expect(builtIcons($this->root, 'alpha'))->toBe(['arrow-left', 'dots', 'home']);
    });

    it('adds the declared icons to the ones asked for when merging', function () {
        writeInstalledPackages($this->root, ['acme/shop' => ['alpha' => ['dots']]]);
        Config::set('flux-icons.icons', null);

        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'home', '--merge' => true, '--no-interaction' => true])
            ->assertExitCode(0);

        expect(builtIcons($this->root, 'alpha'))->toBe(['dots', 'home']);
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

    it('installs the vendor\'s package first when node_modules lacks it', function () {
        File::delete("{$this->root}/node_modules/alpha/package.json");
        $this->app->bind(NpmManager::class, fn () => new NpmManager(binary: fakeNpm($this->root)));

        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'home', '--no-interaction' => true])
            ->assertExitCode(0);

        expect(fakeNpmCalls($this->root))->toBe(["{$this->root} install alpha --save --loglevel=error"]);
    });

    it('fails with what npm said when it cannot install the package', function () {
        File::delete("{$this->root}/node_modules/alpha/package.json");
        $this->app->bind(NpmManager::class, fn () => new NpmManager(binary: fakeNpm($this->root, exitCode: 1, stderr: 'E404 Not Found')));

        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'home', '--no-interaction' => true])
            ->expectsOutputToContain('npm could not install alpha: E404 Not Found')
            ->assertExitCode(1);

        expect(builtIcons($this->root, 'alpha'))->toBe([]);
    });

    it('neither pauses nor starts npm after a large build without a terminal', function () {
        $iconsAboveThreshold = 101;
        $secondsWithoutPause = 15;

        foreach (range(1, $iconsAboveThreshold) as $number) {
            foreach (['outline', 'filled', 'duotone'] as $directory) {
                File::copy("{$this->root}/node_modules/alpha/icons/{$directory}/home.svg", "{$this->root}/node_modules/alpha/icons/{$directory}/copy-{$number}.svg");
            }
        }

        Config::set('flux-icons.request_npm_dev', true);
        // an `npm run dev` started through the shell would find this stand-in first
        fakeNpm($this->root);
        $path = (string) getenv('PATH');
        putenv("PATH={$this->root}:{$path}");
        $start = microtime(true);

        // unlike $this->artisan(), which answers prompts from a mock, a prompt here takes its default
        try {
            $exitCode = Artisan::call('flux-icons:build', ['vendor' => 'alpha', '--all' => true, '--no-interaction' => true]);
        } finally {
            putenv("PATH={$path}");
        }

        // the pause is a third of a second per icon, so about 34 seconds here
        expect($exitCode)->toBe(0)
            ->and(Artisan::output())->not->toContain('start it again')
            ->and(microtime(true) - $start)->toBeLessThan($secondsWithoutPause)
            ->and(count(builtIcons($this->root, 'alpha')))->toBeGreaterThan($iconsAboveThreshold)
            ->and(fakeNpmCalls($this->root))->toBe([]);
    });

    it('fails for a vendor that is not registered', function () {
        $this->artisan('flux-icons:build', ['vendor' => 'nope', '--no-interaction' => true])
            ->expectsOutputToContain("Vendor configuration for 'nope' not found.")
            ->assertExitCode(1);
    });

    it('builds with the option values a subclass of the vendor declares', function () {
        Config::set('flux-icons.vendors.alpha', new class extends AlphaStyled
        {
            public function optionDefaults(): array
            {
                return ['style' => 'filled'];
            }
        });

        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'home', '--no-interaction' => true])
            ->expectsOutputToContain('Building icons for vendor: alpha (style=filled)')
            ->assertExitCode(0);

        expect(outlineOf($this->root, 'alpha', 'home'))->toContain('M11.47 3.84');
    });

    it('lets --options override the vendor\'s own values for one build', function () {
        Config::set('flux-icons.vendors.alpha', AlphaStyled::class);

        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'home', '--options' => ['style=filled'], '--no-interaction' => true])
            ->assertExitCode(0);

        expect(outlineOf($this->root, 'alpha', 'home'))->toContain('M11.47 3.84');
    });

    it('asks for each option it was not given, offering the value the vendor builds with', function () {
        Config::set('flux-icons.vendors.alpha', AlphaStyled::class);

        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'home'])
            ->expectsChoice('Which style do you want to build alpha with?', 'filled', ['outline', 'filled'])
            ->assertExitCode(0);

        expect(outlineOf($this->root, 'alpha', 'home'))->toContain('M11.47 3.84');
    });

    it('refuses an option value the vendor does not take', function () {
        Config::set('flux-icons.vendors.alpha', AlphaStyled::class);

        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--options' => ['style=bold'], '--no-interaction' => true])
            ->expectsOutputToContain('[bold] is not a valid value for option [style] of vendor [alpha]. Allowed: outline, filled.')
            ->assertExitCode(1);

        expect(builtIcons($this->root, 'alpha'))->toBe([]);
    });

    it('refuses an option given without a value', function () {
        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--options' => ['style'], '--no-interaction' => true])
            ->expectsOutputToContain('Give each option as name=value: [style] has no value.')
            ->assertExitCode(1);
    });

    it('fails with the reason when the vendor class defaults to a value it does not take', function () {
        Config::set('flux-icons.vendors.alpha', new class extends AlphaStyled
        {
            public function optionDefaults(): array
            {
                return ['style' => 'bold'];
            }
        });

        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--no-interaction' => true])
            ->expectsOutputToContain('[bold] is not a valid value for option [style] of vendor [alpha]')
            ->assertExitCode(1);
    });

    it('builds from the default vendor when none is named', function () {
        Config::set('flux-icons.defaults.vendor', 'alpha');

        $this->artisan('flux-icons:build', ['--icons' => 'home', '--no-interaction' => true])
            ->assertExitCode(0);

        expect(builtIcons($this->root, 'alpha'))->toBe(['home']);
    });

    it('asks for a vendor when none is named and there is no default', function () {
        $this->artisan('flux-icons:build', ['--icons' => 'home', '--no-interaction' => true])
            ->expectsOutputToContain('Name the vendor to build from, or set defaults.vendor in config/flux-icons.php.')
            ->assertExitCode(1);
    });

    it('names defaults.vendor when the vendor it names is not registered', function () {
        // material-icons publishes to material, so the namespace is an easy key to mistake
        Config::set('flux-icons.defaults.vendor', 'material');

        $this->artisan('flux-icons:build', ['--icons' => 'home', '--no-interaction' => true])
            ->expectsOutputToContain("defaults.vendor in config/flux-icons.php is 'material', which is not a registered vendor key. Registered: alpha")
            ->assertExitCode(1);
    });

    it('asks for a vendor when defaults.vendor is empty, as when it is not set', function () {
        Config::set('flux-icons.defaults.vendor', '');

        $this->artisan('flux-icons:build', ['--icons' => 'home'])
            ->expectsChoice('From which vendor do you want to build icons?', 'alpha', ['alpha', 'beta', 'epsilon'])
            ->assertExitCode(0);

        expect(builtIcons($this->root, 'alpha'))->toBe(['home']);
    });

    it('fails with the reason when the vendor class gives an option no default', function () {
        Config::set('flux-icons.vendors.alpha', AlphaUnstyled::class);

        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--no-interaction' => true])
            ->expectsOutputToContain('Vendor [alpha] has no default for option [style]. Return one of outline, filled for it from')
            ->assertExitCode(1);

        expect(builtIcons($this->root, 'alpha'))->toBe([]);
    });

    it('refuses --icons together with --all', function () {
        $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'home', '--all' => true, '--no-interaction' => true])
            ->assertExitCode(1);

        expect(builtIcons($this->root, 'alpha'))->toBe([]);
    });
});
