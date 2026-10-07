<?php

use Ympact\FluxIcons\Support\VendorRegistry;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Vendors\Phosphor;
use Ympact\FluxIcons\Vendors\Tabler;

it('derives the vendor key and namespace from the class name', function () {
    $tabler = new Tabler;

    expect($tabler->key())->toBe('tabler')
        ->and($tabler->namespace())->toBe('tabler')
        ->and($tabler->name())->toBe('Tabler');
});

it('defines every variant flux asks for', function () {
    expect((new Tabler)->variants()->missingFluxVariants())->toBe([]);
});

it('resolves tabler mini and micro from the solid sources', function () {
    $variants = (new Tabler)->resolvedVariants();

    expect($variants->get('mini')->getSource()->directory())->toBe('filled')
        ->and($variants->get('micro')->getSource()->directory())->toBe('filled')
        ->and($variants->get('mini')->getTemplate())->toBe(Template::Solid);
});

it('keeps an adjustable stroke on the tabler outline variant only', function () {
    $variants = (new Tabler)->resolvedVariants();

    expect($variants->get('outline')->getStroke()?->width)->toBe(1.5)
        ->and($variants->get('solid')->getStroke())->toBeNull();
});

it('resolves tabler source directories against the package path', function () {
    $tabler = new Tabler;
    $packagePath = 'node_modules/'.$tabler->package().'/'.$tabler->basePath();

    expect($tabler->resolvedVariants()->get('outline')->getSource()->path($packagePath))
        ->toBe('node_modules/@tabler/icons/icons/outline');
});

it('exposes a duotone variant on top of the flux variants', function () {
    $variants = (new Phosphor)->variants();

    expect($variants->missingFluxVariants())->toBe([])
        ->and($variants->vendorVariants()->keys()->all())->toBe(['duotone']);
});

it('describes the two layers of a phosphor duotone icon', function () {
    $duotone = (new Phosphor)->variants()->get('duotone');

    expect($duotone->isMultitone())->toBeTrue()
        ->and($duotone->getFills()->pluck('name')->all())->toBe(['background', 'foreground']);
});

it('matches the phosphor duotone layers against the source paths', function () {
    $duotone = (new Phosphor)->variants()->get('duotone');
    [$background, $foreground] = $duotone->getFills()->all();

    // the background path of a phosphor duotone icon is the one drawn at 20% opacity
    expect($background->matches(['opacity' => '0.2']))->toBeTrue()
        ->and($background->matches([]))->toBeFalse()
        ->and($foreground->matches([]))->toBeTrue();
});

it('builds phosphor icons from the regular weight by default', function () {
    $variants = (new Phosphor)->variants();

    expect($variants->get('outline')->getSource()->directory())->toBe('regular')
        ->and($variants->get('outline')->getSource()->fileName('heart'))->toBe('heart');
});

it('suffixes the file name with the weight for every other weight', function () {
    $variants = (new Phosphor)->withOptions(['weight' => 'bold'])->variants();

    expect($variants->get('outline')->getSource()->directory())->toBe('bold')
        ->and($variants->get('outline')->getSource()->fileName('heart'))->toBe('heart-bold');
});

it('suffixes the duotone and fill sources with their own name', function () {
    $variants = (new Phosphor)->variants();

    expect($variants->get('duotone')->getSource()->fileName('heart'))->toBe('heart-duotone')
        ->and($variants->get('solid')->getSource()->fileName('heart'))->toBe('heart-fill');
});

it('leaves the original vendor untouched when options are selected', function () {
    $vendor = new Phosphor;
    $bold = $vendor->withOptions(['weight' => 'bold']);

    expect($bold)->not->toBe($vendor)
        ->and($vendor->option('weight'))->toBeNull()
        ->and($bold->option('weight'))->toBe('bold')
        ->and($vendor->variants()->get('outline')->getSource()->directory())->toBe('regular');
});

it('does not leak selected options into the instance the registry hands out', function () {
    $registry = new VendorRegistry(['phosphor' => Phosphor::class]);

    $registry->get('phosphor')->withOptions(['weight' => 'bold']);

    expect($registry->get('phosphor')->option('weight'))->toBeNull();
});

it('accumulates options across successive calls', function () {
    $vendor = (new Phosphor)->withOptions(['weight' => 'thin'])->withOptions(['weight' => 'bold']);

    expect($vendor->option('weight'))->toBe('bold');
});

it('rejects an option the vendor does not expose', function () {
    (new Phosphor)->withOptions(['ratio' => '4x3']);
})->throws(InvalidArgumentException::class, 'has no option [ratio]');

it('rejects a value the option does not allow', function () {
    (new Phosphor)->withOptions(['weight' => 'ultra']);
})->throws(InvalidArgumentException::class, 'not a valid value for option [weight]');

it('exposes the allowed values of its options', function () {
    expect((new Phosphor)->options())->toBe([
        'weight' => ['thin', 'light', 'regular', 'bold'],
    ]);
});
