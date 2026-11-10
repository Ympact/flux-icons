<?php

use Ympact\FluxIcons\Variants\Fill;

it('treats a fill without matchers as the catch-all layer', function () {
    $fill = new Fill('foreground');

    expect($fill->isCatchAll())->toBeTrue()
        ->and($fill->matches([]))->toBeTrue()
        ->and($fill->matches(['opacity' => '0.2']))->toBeTrue();
});

it('selects paths carrying the opacity of the layer', function () {
    $fill = new Fill('background', matchOpacity: 0.2);

    expect($fill->matches(['opacity' => '0.2']))->toBeTrue()
        ->and($fill->matches(['opacity' => '0.5']))->toBeFalse()
        ->and($fill->matches([]))->toBeFalse();
});

it('accepts fill-opacity as well as opacity', function () {
    $fill = new Fill('background', matchOpacity: 0.2);

    expect($fill->matches(['fill-opacity' => '0.2']))->toBeTrue();
});

it('selects paths carrying the colour of the layer', function () {
    $fill = new Fill('accent', matchColor: '#FF0000');

    expect($fill->matches(['fill' => '#ff0000']))->toBeTrue()
        ->and($fill->matches(['fill' => '#00ff00']))->toBeFalse();
});

it('requires every matcher to hold', function () {
    $fill = new Fill('background', matchOpacity: 0.2, matchColor: '#000');

    expect($fill->matches(['opacity' => '0.2', 'fill' => '#000']))->toBeTrue()
        ->and($fill->matches(['opacity' => '0.2', 'fill' => '#fff']))->toBeFalse();
});

it('renders a monotone layer in the current text colour', function () {
    expect((new Fill)->attributes())->toBe(['fill' => 'currentColor']);
});

it('renders a layer at the opacity it was given', function () {
    $fill = new Fill('background', matchOpacity: 0.2, opacity: 0.2);

    expect($fill->attributes())->toBe([
        'fill' => 'currentColor',
        'opacity' => '0.2',
    ]);
});

it('renders a layer in an explicit colour', function () {
    $fill = new Fill('accent', color: 'var(--color-accent)');

    expect($fill->attributes())->toBe(['fill' => 'var(--color-accent)']);
});
