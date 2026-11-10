<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Ympact\FluxIcons\FluxIconsServiceProvider;
use Ympact\FluxIcons\Services\IconBuilder;

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
