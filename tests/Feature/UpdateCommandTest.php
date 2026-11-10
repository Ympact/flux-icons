<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Ympact\FluxIcons\FluxIconsServiceProvider;
use Ympact\FluxIcons\Services\IconBuilder;
use Ympact\FluxIcons\Services\Vendors\Healthicons;

/*
 * Updates a project in which one alpha icon (home) has been built.
 */
beforeEach(function () {
    $this->project = enterTemporaryProject();

    $this->app->register(FluxIconsServiceProvider::class);
    Config::set('flux-icons', fixtureConfig());

    (new IconBuilder('alpha', ['home']))->buildIcons();

    $this->component = resource_path('views/flux/icon/alpha/home.blade.php');
    File::put($this->component, 'stale');
});

afterEach(fn () => leaveTemporaryProject($this->project));

it('updates the package and rebuilds the built icons', function () {
    $this->artisan('flux-icons:update')->assertExitCode(0);

    expect(npmCalls($this->project))->toContain('update alpha/icons -s')
        ->and(File::get($this->component))->toContain('<svg');
});

it('fails when the package cannot be updated', function () {
    putenv('FAKE_NPM_UPDATE_EXIT=1');

    $this->artisan('flux-icons:update')
        ->expectsOutputToContain('FAIL')
        ->assertExitCode(1);
});

it('fails when the icons cannot be rebuilt', function () {
    File::delete($this->project.'/package-lock.json');

    $this->artisan('flux-icons:update')->assertExitCode(1);
});

it('refuses an empty --vendor instead of updating every vendor', function () {
    $this->artisan('flux-icons:update', ['--vendor' => ''])->assertExitCode(1);

    expect(npmCalls($this->project))->not->toContain('update alpha/icons -s')
        ->and(File::get($this->component))->toBe('stale');
});

it('rebuilds an icon that the icon_name callback renamed', function () {
    Config::set('flux-icons.vendors.alpha.icon_name', [Healthicons::class, 'name']);

    foreach (['outline', 'filled'] as $directory) {
        File::copy(
            "{$this->project}/tests/vendor/alpha/icons/{$directory}/home.svg",
            "{$this->project}/tests/vendor/alpha/icons/{$directory}/home_alt.svg"
        );
    }

    (new IconBuilder('alpha', ['home_alt']))->buildIcons();

    $renamed = resource_path('views/flux/icon/alpha/home-alt.blade.php');
    File::put($renamed, 'stale');

    $this->artisan('flux-icons:update')->assertExitCode(0);

    expect(File::get($renamed))->toContain('<svg');
});

it('fails when a requested directory belongs to no configured vendor', function () {
    File::ensureDirectoryExists(resource_path('views/flux/icon/retired'));
    File::put(resource_path('views/flux/icon/retired/old.blade.php'), 'stale');

    $this->artisan('flux-icons:update', ['--vendor' => 'retired'])->assertExitCode(1);
});

it('skips such a directory when it was not asked for', function () {
    File::ensureDirectoryExists(resource_path('views/flux/icon/retired'));
    File::put(resource_path('views/flux/icon/retired/old.blade.php'), 'stale');

    $this->artisan('flux-icons:update')->assertExitCode(0);
});
