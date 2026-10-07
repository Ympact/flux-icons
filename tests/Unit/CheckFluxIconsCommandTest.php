<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Console\CheckFluxIconsCommand;

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

    config()->set('flux-icons.vendors', ['tabler' => ['namespace' => 'tabler']]);
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
    config()->set('flux-icons.vendors', ['material-icons' => ['namespace' => 'material']]);

    $this->artisan('flux-icons:check', ['--path' => [$root.'/resources/views'], '--build' => true])
        ->expectsOutputToContain('flux-icons:build material-icons --icons=missing --merge')
        ->assertExitCode(1);
});

it('ignores vendors other than the one asked for', function () {
    $root = fakeApp('<flux:icon.mdi.missing />', []);
    app()->setBasePath($root);
    config()->set('flux-icons.vendors', [
        'tabler' => ['namespace' => 'tabler'],
        'mdi' => ['namespace' => 'mdi'],
    ]);

    $this->artisan('flux-icons:check', ['vendor' => 'tabler', '--path' => [$root.'/resources/views']])
        ->assertExitCode(0);
});

it('fails when no vendors are configured at all', function () {
    config()->set('flux-icons.vendors', []);

    $this->artisan('flux-icons:check')
        ->expectsOutputToContain('No vendors are configured')
        ->assertExitCode(1);
});
