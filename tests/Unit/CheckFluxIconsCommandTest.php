<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Console\CheckFluxIconsCommand;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\AlphaMisstyled;
use Ympact\FluxIcons\Vendors\Healthicons;
use Ympact\FluxIcons\Vendors\MaterialIcons;
use Ympact\FluxIcons\Vendors\Mdi;
use Ympact\FluxIcons\Vendors\Tabler;

/**
 * Lay out a fake application: a template referencing icons, and a resources/views/flux/icon
 * tree holding the ones that have been built.
 *
 * @param  array<int, string>  $built  Icons that exist, as `namespace/name`
 */
function fakeApp(string $template, array $built = []): string
{
    $root = sys_get_temp_dir().'/flux-icons-check-'.Str::random(8);

    mkdir($root.'/resources/views', 0777, true);
    file_put_contents($root.'/resources/views/page.blade.php', $template);

    foreach ($built as $icon) {
        [$namespace, $name] = explode('/', $icon);
        @mkdir($root."/resources/views/flux/icon/{$namespace}", 0777, true);
        file_put_contents($root."/resources/views/flux/icon/{$namespace}/{$name}.blade.php", '<svg/>');
    }

    return $root;
}

beforeEach(function () {
    // The test case registers no package providers, so the command is registered directly
    // rather than through the service provider's boot.
    $this->app[Kernel::class]->registerCommand(new CheckFluxIconsCommand);

    config()->set('flux-icons.vendors', ['tabler' => Tabler::class]);
});

it('passes when every referenced icon has been built', function () {
    $root = fakeApp('<flux:button icon="tabler.check" />', ['tabler/check']);
    app()->setBasePath($root);

    $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views']])
        ->expectsOutputToContain('resolve to a built icon')
        ->assertExitCode(0);
});

it('fails and names the icon that was never built', function () {
    $root = fakeApp('<flux:button icon="tabler.missing" />');
    app()->setBasePath($root);

    // One substring per written line: each expectation is matched against a single write,
    // and both halves of this sit on the same one.
    $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views']])
        ->expectsOutputToContain('tabler.missing has not been built')
        ->assertExitCode(1);
});

it('calls a doubled namespace malformed rather than unbuilt', function () {
    $root = fakeApp('<flux:link icon="tabler.tabler.check" />', ['tabler/check']);
    app()->setBasePath($root);

    $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views']])
        ->expectsOutputToContain('tabler.tabler.check is not a valid icon name')
        ->assertExitCode(1);
});

it('offers the build command for the icons that are merely missing', function () {
    $root = fakeApp('<flux:button icon="tabler.alpha" /><flux:button icon="tabler.beta" />');
    app()->setBasePath($root);

    $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views'], '--build' => true])
        ->expectsOutputToContain('flux-icons:build tabler --icons=alpha,beta --merge')
        ->assertExitCode(1);
});

it('suggests the build command by config key when that differs from the namespace', function () {
    $root = fakeApp('<flux:button icon="material.missing" />');
    app()->setBasePath($root);
    config()->set('flux-icons.vendors', ['material-icons' => MaterialIcons::class]);

    $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views'], '--build' => true])
        ->expectsOutputToContain('flux-icons:build material-icons --icons=missing --merge')
        ->assertExitCode(1);
});

it('ignores vendors other than the one asked for', function () {
    $root = fakeApp('<flux:icon.mdi.missing />', []);
    app()->setBasePath($root);
    config()->set('flux-icons.vendors', [
        'tabler' => Tabler::class,
        'mdi' => Mdi::class,
    ]);

    $this->artisan('flux-icons:check', ['vendor' => 'tabler', '--path' => [$root.'/resources/views']])
        ->assertExitCode(0);
});

it('reports an unknown vendor without claiming none are configured', function () {
    $this->artisan('flux-icons:check', ['vendor' => 'nope'])
        ->expectsOutputToContain("Vendor configuration for 'nope' not found")
        ->expectsOutputToContain('Configured vendors: tabler')
        ->doesntExpectOutputToContain('No vendors are configured')
        ->assertExitCode(1);
});

it('fails when no vendors are configured at all', function () {
    config()->set('flux-icons.vendors', []);

    $this->artisan('flux-icons:check')
        ->expectsOutputToContain('No vendors are configured')
        ->assertExitCode(1);
});

/**
 * A built icon whose header lists the variants it was built with, as the builder writes it.
 */
function builtWithVariants(string $root, string $icon, string $variants): void
{
    [$namespace, $name] = explode('/', $icon);
    @mkdir($root."/resources/views/flux/icon/{$namespace}", 0777, true);
    file_put_contents($root."/resources/views/flux/icon/{$namespace}/{$name}.blade.php", "{{--\n  Icon:     {$name}\n  Variants: {$variants}\n--}}\n<svg/>");
}

it('reports a literal variant the icon was not built with', function () {
    $root = fakeApp('<flux:icon.tabler.home variant="mini" />'."\n".'<flux:icon.tabler.home variant="duotone" />');
    builtWithVariants($root, 'tabler/home', 'outline, solid, mini, micro');
    app()->setBasePath($root);

    $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views']])
        ->expectsOutputToContain('tabler.home is not built with the duotone variant')
        ->doesntExpectOutputToContain('mini variant')
        ->assertExitCode(1);
});

it('trusts a variant on an icon built before its header listed them', function () {
    $root = fakeApp('<flux:icon.tabler.home variant="duotone" />', ['tabler/home']);
    app()->setBasePath($root);

    $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views']])
        ->assertExitCode(0);
});

it('does not count the icons it built as references to themselves', function () {
    $root = fakeApp('<flux:icon.tabler.home />');
    builtWithVariants($root, 'tabler/home', 'outline, solid, mini, micro');
    file_put_contents($root.'/resources/views/flux/icon/tabler/home.blade.php', '<flux:icon.tabler.home />', FILE_APPEND);
    app()->setBasePath($root);

    $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views']])
        ->expectsOutputToContain('All 1 icon references resolve')
        ->assertExitCode(0);
});

it('looks in the application\'s php as well as its views by default', function () {
    $root = fakeApp('<flux:icon.tabler.home />', ['tabler/home']);
    @mkdir($root.'/app/Enums', 0777, true);
    file_put_contents($root.'/app/Enums/Status.php', "<?php\n\nreturn 'tabler.lock';\n");
    app()->setBasePath($root);
    config()->set('view.paths', [$root.'/resources/views']);
    View::getFinder()->setPaths([$root.'/resources/views']);

    $this->artisan('flux-icons:check')
        ->expectsOutputToContain('app/Enums/Status.php')
        ->expectsOutputToContain('tabler.lock has not been built')
        ->assertExitCode(1);
});

it('reports as json for ci and agents', function () {
    $root = fakeApp("<flux:icon.tabler.home />\n<flux:icon.tabler.missing />\n<flux:icon.tabler.tabler.x />\n<flux:icon.tabler.home variant=\"duotone\" />");
    builtWithVariants($root, 'tabler/home', 'outline, solid, mini, micro');
    app()->setBasePath($root);

    $exitCode = Artisan::call('flux-icons:check', ['--path' => [$root.'/resources/views'], '--json' => true]);

    expect($exitCode)->toBe(1)
        ->and(json_decode(Artisan::output(), true))->toBe([
            'references' => 4,
            'unresolved' => [
                ['file' => 'resources/views/page.blade.php', 'line' => 2, 'package' => null, 'name' => 'tabler.missing', 'vendor' => 'tabler', 'variant' => null, 'reason' => 'not-built'],
                ['file' => 'resources/views/page.blade.php', 'line' => 3, 'package' => null, 'name' => 'tabler.tabler.x', 'vendor' => 'tabler', 'variant' => null, 'reason' => 'malformed'],
                ['file' => 'resources/views/page.blade.php', 'line' => 4, 'package' => null, 'name' => 'tabler.home', 'vendor' => 'tabler', 'variant' => 'duotone', 'reason' => 'unknown-variant'],
            ],
            'build' => [
                ['vendor' => 'tabler', 'icons' => ['missing'], 'command' => 'php artisan flux-icons:build tabler --icons=missing --merge'],
            ],
        ]);
});

it('reports a problem that stops the check as json too', function () {
    $exitCode = Artisan::call('flux-icons:check', ['vendor' => 'nope', '--json' => true]);

    expect($exitCode)->toBe(1)
        ->and(json_decode(Artisan::output(), true))->toBe(['error' => "Vendor configuration for 'nope' not found. Configured vendors: tabler"]);
});

describe('icons packages declare', function () {
    it('reports a declared icon that has not been built, naming the package', function () {
        $root = fakeApp('<flux:icon.tabler.home />', ['tabler/home', 'tabler/key']);
        writeInstalledPackages($root, ['acme/shop' => ['tabler' => ['key', 'map-pin']]]);
        app()->setBasePath($root);

        $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views'], '--build' => true])
            ->expectsOutputToContain('acme/shop')
            ->expectsOutputToContain('tabler.map-pin has not been built')
            ->expectsOutputToContain('flux-icons:build tabler --icons=map-pin --merge')
            ->assertExitCode(1);
    });

    it('counts the declared icons that were built', function () {
        $root = fakeApp('<flux:icon.tabler.home />', ['tabler/home', 'tabler/key']);
        writeInstalledPackages($root, ['acme/shop' => ['tabler' => ['key']]]);
        app()->setBasePath($root);

        $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views']])
            ->expectsOutputToContain('All 2 icon references resolve')
            ->assertExitCode(0);
    });

    it('reports an icon declared for a namespace no configured vendor publishes to', function () {
        $root = fakeApp('<flux:icon.tabler.home />', ['tabler/home']);
        writeInstalledPackages($root, ['acme/shop' => ['heroicons' => ['bolt']]]);
        app()->setBasePath($root);

        $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views']])
            ->expectsOutputToContain('heroicons.bolt has no configured vendor publishing to heroicons')
            ->assertExitCode(1);

        // checking one vendor leaves every other namespace alone
        $this->artisan('flux-icons:check', ['vendor' => 'tabler', '--path' => [$root.'/resources/views']])
            ->assertExitCode(0);
    });

    it('looks for a declared file name under the name the vendor publishes it as', function () {
        $root = fakeApp('', ['healthicons/foo-bar']);
        writeInstalledPackages($root, ['acme/clinic' => ['healthicons' => ['Foo_Bar']]]);
        app()->setBasePath($root);
        config()->set('flux-icons.vendors', ['healthicons' => Healthicons::class]);

        $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views']])
            ->expectsOutputToContain('All 1 icon references resolve')
            ->assertExitCode(0);
    });

    it('gives an icon declared for an unknown namespace no vendor in json', function () {
        $root = fakeApp('', []);
        writeInstalledPackages($root, ['acme/shop' => ['material-icons' => ['zap']]]);
        app()->setBasePath($root);
        config()->set('flux-icons.vendors', ['material-icons' => MaterialIcons::class]);

        Artisan::call('flux-icons:check', ['--path' => [$root.'/resources/views'], '--json' => true]);

        expect(json_decode(Artisan::output(), true)['unresolved'][0])->toMatchArray(['name' => 'material-icons.zap', 'vendor' => null, 'reason' => 'unknown-vendor']);
    });

    it('keeps a package name from styling or escaping the console', function () {
        $root = fakeApp('', []);
        writeInstalledPackages($root, ["acme/</><fg=green>ok\e[2J" => ['tabler' => ['map-pin']]]);
        app()->setBasePath($root);

        $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views']])
            ->expectsOutputToContain('acme/</><fg=green>ok?[2J')
            ->assertExitCode(1);
    });

    it('names the package in json, where a scanned reference has a file and line', function () {
        $root = fakeApp('<flux:icon.tabler.home />', ['tabler/home']);
        writeInstalledPackages($root, ['acme/shop' => ['tabler' => ['map-pin']]]);
        app()->setBasePath($root);

        Artisan::call('flux-icons:check', ['--path' => [$root.'/resources/views'], '--json' => true]);

        expect(json_decode(Artisan::output(), true)['unresolved'])->toBe([
            ['file' => null, 'line' => null, 'package' => 'acme/shop', 'name' => 'tabler.map-pin', 'vendor' => 'tabler', 'variant' => null, 'reason' => 'not-built'],
        ]);
    });
});

it('reports a vendor class whose option default it does not take, rather than throwing', function () {
    config()->set('flux-icons.vendors', ['tabler' => Tabler::class, 'alpha' => AlphaMisstyled::class]);

    $this->artisan('flux-icons:check', ['tabler'])
        ->expectsOutputToContain('[bold] is not a valid value for option [style] of vendor [alpha]')
        ->assertExitCode(1);
});

it('writes json as it is, past the console formatter', function () {
    $exitCode = Artisan::call('flux-icons:check', ['vendor' => '<info>x</info>', '--json' => true]);

    expect($exitCode)->toBe(1)
        ->and(json_decode(Artisan::output(), true)['error'] ?? null)->toStartWith("Vendor configuration for '<info>x</info>' not found.");
});

it('writes valid json for a file name that is not utf-8', function () {
    $root = fakeApp('');
    file_put_contents($root."/resources/views/caf\xE9.blade.php", '<flux:icon.tabler.missing />');
    app()->setBasePath($root);

    $exitCode = Artisan::call('flux-icons:check', ['--path' => [$root.'/resources/views'], '--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1)
        ->and($report['unresolved'][0]['name'] ?? null)->toBe('tabler.missing')
        ->and($report['unresolved'][0]['file'] ?? null)->toBe("resources/views/caf\u{FFFD}.blade.php");
});

it('fails for a directory to scan that does not exist, rather than passing on nothing', function () {
    $this->artisan('flux-icons:check', ['--path' => ['/no/such/directory']])
        ->expectsOutputToContain('No such directory to scan: /no/such/directory.')
        ->assertExitCode(1);
});

it('quotes an argument of the suggested build command that holds more than a name', function () {
    $command = new class extends CheckFluxIconsCommand
    {
        public function quoted(string $argument): string
        {
            return $this->shellArgument($argument);
        }
    };

    expect($command->quoted('tabler'))->toBe('tabler')
        ->and($command->quoted('home,map-pin'))->toBe('home,map-pin')
        ->and($command->quoted('x$(touch /tmp/pwned)'))->toBe("'x$(touch /tmp/pwned)'");
});
