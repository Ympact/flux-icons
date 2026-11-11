<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Console\CheckFluxIconsCommand;
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
                ['file' => 'resources/views/page.blade.php', 'line' => 2, 'name' => 'tabler.missing', 'vendor' => 'tabler', 'variant' => null, 'reason' => 'not-built'],
                ['file' => 'resources/views/page.blade.php', 'line' => 3, 'name' => 'tabler.tabler.x', 'vendor' => 'tabler', 'variant' => null, 'reason' => 'malformed'],
                ['file' => 'resources/views/page.blade.php', 'line' => 4, 'name' => 'tabler.home', 'vendor' => 'tabler', 'variant' => 'duotone', 'reason' => 'unknown-variant'],
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
