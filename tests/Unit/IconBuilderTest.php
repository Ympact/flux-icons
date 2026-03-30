<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Tests\Support\Fallbacks;
use Ympact\FluxIcons\Services\IconBuilder;
use Ympact\FluxIcons\Types\Icon;

beforeEach(function () {
    Config::set('flux-icons', fixtureConfig());
    $this->builder = new IconBuilder;
});

it('configures a known vendor', function () {
    $this->builder->setVendor('alpha');

    expect($this->builder)->toBeInstanceOf(IconBuilder::class);
});

it('rejects an unknown vendor', function () {
    $this->builder->setVendor('nonexistent');
})->throws(Exception::class, 'Vendor nonexistent not found in config file');

it('accepts icon filters as an array', function () {
    $this->builder->setVendor('alpha')->setIcons(['home', 'arrow-left']);

    expect($this->builder->getAvailableIcons('outline'))->toBeInstanceOf(Collection::class);
});

it('accepts icon filters as a comma separated string', function () {
    $this->builder->setVendor('alpha')->setIcons('home,arrow-left');

    expect($this->builder->getAvailableIcons('outline'))->toBeInstanceOf(Collection::class);
});

it('returns all configured vendors from config', function () {
    $vendors = IconBuilder::getAvailableVendors();

    expect($vendors)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(4)
    ->and(array_keys($vendors->toArray()))->toMatchSortedValues(['alpha', 'beta', 'gamma', 'epsilon']);
});

it('discovers alpha outline icons from the source directory', function () {
    $this->builder->setVendor('alpha');
    $icons = $this->builder->getAvailableIcons('outline');
    $names = $icons->map(fn ($file) => pathinfo($file, PATHINFO_FILENAME))->values()->toArray();

    expect($icons)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(3)
        ->and($names)->toContain('arrow-left', 'home', 'dots');
});

it('discovers alpha solid icons from the source directory', function () {
    $this->builder->setVendor('alpha');

    expect($this->builder->getAvailableIcons('solid'))->toHaveCount(2);
});

it('discovers beta icons using the dir prefix suffix source format', function () {
    $this->builder->setVendor('beta');
    $names = $this->builder->getAvailableIcons('outline')
        ->map(fn ($file) => pathinfo($file, PATHINFO_FILENAME))
        ->values()
        ->toArray();

    expect($names)->toContain('arrow-left_24_regular');
});

it('resolves the current mini defaults', function () {
    $this->builder->setVendor('alpha');

    expect($this->builder->variantProp('mini', 'template'))->toBe('solid')
        ->and($this->builder->variantProp('mini', 'size'))->toBe(24);
});

it('resolves the current micro defaults', function () {
    $this->builder->setVendor('alpha');

    expect($this->builder->variantProp('micro', 'template'))->toBe('solid')
        ->and($this->builder->variantProp('micro', 'size'))->toBe(24);
});

it('keeps epsilon mini mapped to the current resolved template', function () {
    $this->builder->setVendor('epsilon');

    expect($this->builder->variantProp('mini', 'template'))->toBe('solid');
});

it('returns false when a variant has no fallback', function () {
    $this->builder->setVendor('alpha');

    expect($this->builder->determineFallback('outline', makeBaseBuilderIcon($this->builder)))->toBeFalse();
});

it('falls back to the base variant when fallback is default', function () {
    $this->builder->setVendor('alpha');

    expect($this->builder->determineFallback('solid', makeBaseBuilderIcon($this->builder)))->toBe('outline');
});

it('returns a configured string fallback variant', function () {
    Config::set('flux-icons.vendors.alpha.variants.solid.fallback', 'outline');

    $builder = new IconBuilder;
    $builder->setVendor('alpha');

    expect($builder->determineFallback('solid', makeBaseBuilderIcon($builder)))->toBe('outline');
});

it('returns a configured callable fallback variant', function () {
    Config::set('flux-icons.vendors.alpha.variants.solid.fallback', [Fallbacks::class, 'toOutline']);

    $builder = new IconBuilder;
    $builder->setVendor('alpha');

    expect($builder->determineFallback('solid', makeBaseBuilderIcon($builder)))->toBe('outline');
});

it('returns a variant icon file when the target exists', function () {
    $this->builder->setVendor('alpha');

    $file = $this->builder->getVariantIconFile('solid', 'arrow-left', makeBaseBuilderIcon($this->builder));

    expect($file)->not->toBeNull()
        ->and($file)->toEndWith('arrow-left.svg');
});

it('returns null for a missing variant icon file', function () {
    $this->builder->setVendor('alpha');

    expect($this->builder->getVariantIconFile('solid', 'nonexistent-icon', makeBaseBuilderIcon($this->builder)))->toBeNull();
});

it('resolves the epsilon solid icon file', function () {
    $this->builder->setVendor('epsilon');

    $file = $this->builder->getVariantIconFile(
        'solid',
        'home',
        makeBaseBuilderIcon($this->builder, fixturePath('vendor/epsilon/icons/icon-home-outline.svg'))
    );

    expect($file)->not->toBeNull();
});

it('builds an icon object for a source file', function () {
    $this->builder->setVendor('alpha');

    $icon = $this->builder->buildIcon('outline', alphaOutlineFile());

    expect($icon)
        ->toBeInstanceOf(Icon::class)
        ->and($icon->getName())->toBe('arrow-left');
});
