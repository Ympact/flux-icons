<?php

use Ympact\FluxIcons\Services\Vendors\Bootstrap;
use Ympact\FluxIcons\Services\Vendors\Tabler;
use Ympact\FluxIcons\Tests\Vendor\Beta\Beta;
use Ympact\FluxIcons\Tests\Vendor\Epsilon\Epsilon;

it('treats non-fill bootstrap icons as outline variants', function () {
    $icons = null;

    expect(Bootstrap::outlineFilter('/path/to/icons/arrow-left.svg', $icons))->toBeTrue();
});

it('excludes fill bootstrap icons from outline variants', function () {
    $icons = null;

    expect(Bootstrap::outlineFilter('/path/to/icons/arrow-left-fill.svg', $icons))->toBeFalse();
});

it('treats fill bootstrap icons as solid variants', function () {
    $icons = null;

    expect(Bootstrap::solidFilter('/path/to/icons/arrow-left-fill.svg', $icons))->toBeTrue();
});

it('excludes non-fill bootstrap icons from solid variants', function () {
    $icons = null;

    expect(Bootstrap::solidFilter('/path/to/icons/arrow-left.svg', $icons))->toBeFalse();
});

it('removes the tabler bounding box path during transforms', function () {
    $result = Tabler::transform(collect([makeSvgPath('M0 0h24v24H0z'), makeSvgPath('M10 19l-7-7')]), makeVendorTestIcon());

    expect($result)
        ->toHaveCount(1)
        ->and($result->first()->getD())->toBe('M10 19l-7-7');
});

it('keeps non bounding box tabler paths during transforms', function () {
    $result = Tabler::transform(collect([makeSvgPath('M5 12H19'), makeSvgPath('M12 5l7 7-7 7')]), makeVendorTestIcon());

    expect($result)->toHaveCount(2);
});

it('returns an empty collection when tabler transform receives no paths', function () {
    expect(Tabler::transform(collect(), makeVendorTestIcon()))->toHaveCount(0);
});

it('keeps the default tabler stroke width for a normal outline icon', function () {
    $icon = makeVendorTestIcon('outline', 'arrow-left');
    $icon->setStrokeWidth(1.5, true);

    expect(Tabler::strokeWidth($icon))->toBe(1.5);
});

it('bumps the tabler stroke width for small-circle outline icons', function () {
    $icon = makeVendorTestIcon('outline', 'dots');
    $icon->setStrokeWidth(1.5, true);

    expect(Tabler::strokeWidth($icon))->toBe(2);
});

it('leaves tabler stroke width unchanged for non-outline templates', function () {
    $icon = makeVendorTestIcon('solid', 'arrow-left');
    $icon->setStrokeWidth(1.5, true)->setTemplate('solid');

    expect(Tabler::strokeWidth($icon))->toBe(1.5);
});

it('treats epsilon outline files as outline variants', function () {
    $icons = null;

    expect(Epsilon::outlineFilter(
        base_path('tests/vendor/epsilon/icons/icon-home-outline.svg'),
        $icons,
    ))->toBeTrue();
});

it('rejects epsilon solid files when an outline sibling exists', function () {
    $icons = null;

    expect(Epsilon::outlineFilter(
        base_path('tests/vendor/epsilon/icons/icon-home.svg'),
        $icons,
    ))->toBeFalse();
});

it('rejects epsilon outline-named files from the solid variant', function () {
    expect(Epsilon::solidFilter(
        base_path('tests/vendor/epsilon/icons/icon-home-outline.svg')
    ))->toBeFalse();
});

it('accepts epsilon non-outline files for the solid variant', function () {
    expect(Epsilon::solidFilter(
        base_path('tests/vendor/epsilon/icons/icon-home.svg')
    ))->toBeTrue();
});

it('builds the beta filled suffix for solid icons', function () {
    expect(Beta::sourceSolidSuffix('solid', 24))->toBe('_24_filled');
});

it('varies the beta filled suffix by icon size', function () {
    expect(Beta::sourceSolidSuffix('mini', 20))->toBe('_20_filled')
        ->and(Beta::sourceSolidSuffix('micro', 16))->toBe('_16_filled');
});
