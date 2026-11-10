<?php

use Illuminate\Support\Facades\Config;
use Ympact\FluxIcons\FluxIconsServiceProvider;

beforeEach(function () {
    $this->project = enterTemporaryProject();

    $this->app->register(FluxIconsServiceProvider::class);
    Config::set('flux-icons', fixtureConfig());
    Config::set('flux-icons.icons', ['alpha' => ['home']]);
});

afterEach(fn () => leaveTemporaryProject($this->project));

it('merges the requested icons with the configured ones', function () {
    $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--icons' => 'arrow-left', '--merge' => true])
        ->assertExitCode(0);

    expect(builtIcons('alpha'))->toBe(['arrow-left', 'home']);
});

it('builds every icon when all is chosen at the prompt, also with --merge', function () {
    $this->artisan('flux-icons:build', ['vendor' => 'alpha', '--merge' => true])
        ->expectsQuestion('Which icons do you want to build from the vendor?', 'all')
        ->assertExitCode(0);

    expect(builtIcons('alpha'))->toBe(['arrow-left', 'dots', 'home']);
});
