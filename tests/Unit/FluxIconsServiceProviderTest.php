<?php

use Illuminate\Support\Facades\Blade;
use Ympact\FluxIcons\FluxIconsServiceProvider;

function bootBlazeFallbacks($app): void
{
    (new FluxIconsServiceProvider($app))->bootFallbackBlazeDirectivesIfBlazeIsNotInstalled();
}

it('registers fallback blaze directives when nothing else provides them', function () {
    bootBlazeFallbacks($this->app);

    expect(Blade::getCustomDirectives())
        ->toHaveKey('blaze')
        ->toHaveKey('pure')
        ->toHaveKey('unblaze')
        ->toHaveKey('endunblaze');
});

it('compiles the fallback blaze directive to nothing', function () {
    bootBlazeFallbacks($this->app);

    $blaze = Blade::getCustomDirectives()['blaze'];

    expect($blaze())->toBe('');
});

it('does not overwrite a blaze directive that is already registered', function () {
    // stand in for livewire/blaze, which registers the real directive in its own boot()
    Blade::directive('blaze', fn () => '<?php /* real blaze */ ?>');

    bootBlazeFallbacks($this->app);

    $blaze = Blade::getCustomDirectives()['blaze'];

    expect($blaze())->toBe('<?php /* real blaze */ ?>');
});
