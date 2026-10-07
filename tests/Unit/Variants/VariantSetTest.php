<?php

use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Fill;
use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\Variant;
use Ympact\FluxIcons\Variants\VariantSet;

function fluxLikeSet(): VariantSet
{
    return VariantSet::make()
        ->variant('outline', fn (Variant $v) => $v
            ->source(Source::dir('outline'))
            ->stroke(new Stroke(1.5))
            ->default()
        )
        ->variant('solid', fn (Variant $v) => $v
            ->template(Template::Solid)
            ->source(Source::dir('filled'))
            ->fallback(Fallback::defaultVariant())
        )
        ->variant('mini', fn (Variant $v) => $v->basedOn('solid'))
        ->variant('micro', fn (Variant $v) => $v->basedOn('solid'));
}

it('marks the variant flagged as default', function () {
    expect(fluxLikeSet()->defaultName())->toBe('outline');
});

it('falls back to the first variant when none is flagged as default', function () {
    $set = VariantSet::make()
        ->variant('solid')
        ->variant('outline');

    expect($set->defaultName())->toBe('solid');
});

it('throws when a set has no variants at all', function () {
    VariantSet::make()->default();
})->throws(InvalidArgumentException::class, 'at least one variant');

it('reconfigures a variant when it is defined again', function () {
    $set = fluxLikeSet()
        ->variant('solid', fn (Variant $v) => $v->size(32));

    expect($set->get('solid')->getSize())->toBe(32)
        ->and($set->count())->toBe(4);
});

it('drops a variant a vendor cannot supply', function () {
    $set = fluxLikeSet()->without('micro');

    expect($set->names())->toBe(['outline', 'solid', 'mini'])
        ->and($set->missingFluxVariants())->toBe(['micro']);
});

it('reports no missing flux variants for a complete set', function () {
    expect(fluxLikeSet()->missingFluxVariants())->toBe([]);
});

it('gives flux variants the sizes flux renders them at', function () {
    $set = fluxLikeSet()->resolve();

    expect($set->get('outline')->getSize())->toBe(24)
        ->and($set->get('solid')->getSize())->toBe(24)
        ->and($set->get('mini')->getSize())->toBe(20)
        ->and($set->get('micro')->getSize())->toBe(16);
});

it('keeps the flux size of a derived variant instead of inheriting it', function () {
    $set = fluxLikeSet()
        ->variant('solid', fn (Variant $v) => $v->size(32))
        ->resolve();

    expect($set->get('mini')->getSize())->toBe(20);
});

it('inherits template and source from the variant it is based on', function () {
    $set = fluxLikeSet()->resolve();

    expect($set->get('mini')->getTemplate())->toBe(Template::Solid)
        ->and($set->get('mini')->getSource()->directory())->toBe('filled');
});

it('throws when a variant is based on a variant that is not defined', function () {
    VariantSet::make()
        ->variant('outline')
        ->variant('mini', fn (Variant $v) => $v->basedOn('solid'))
        ->resolve();
})->throws(InvalidArgumentException::class, 'is based on [solid]');

it('resolves inheritance through a chain of variants', function () {
    $set = VariantSet::make()
        ->variant('outline', fn (Variant $v) => $v->template(Template::Solid)->source(Source::dir('outline')))
        ->variant('solid', fn (Variant $v) => $v->basedOn('outline'))
        ->variant('mini', fn (Variant $v) => $v->basedOn('solid'))
        ->resolve();

    expect($set->get('mini')->getSource()->directory())->toBe('outline')
        ->and($set->get('mini')->getTemplate())->toBe(Template::Solid);
});

it('resolves inheritance regardless of the order the variants were defined in', function () {
    $set = VariantSet::make()
        ->variant('mini', fn (Variant $v) => $v->basedOn('solid'))
        ->variant('solid', fn (Variant $v) => $v->basedOn('outline')->template(Template::Solid))
        ->variant('outline', fn (Variant $v) => $v->source(Source::dir('outline')))
        ->resolve();

    expect($set->get('mini')->getSource()?->directory())->toBe('outline')
        ->and($set->get('solid')->getSource()?->directory())->toBe('outline');
});

it('keeps the definition order after resolving', function () {
    $set = VariantSet::make()
        ->variant('mini', fn (Variant $v) => $v->basedOn('solid'))
        ->variant('solid', fn (Variant $v) => $v->source(Source::dir('filled')))
        ->resolve();

    // the first defined variant is the implicit default, so the order has to survive
    expect($set->names())->toBe(['mini', 'solid'])
        ->and($set->defaultName())->toBe('mini');
});

it('rejects variants that are based on each other in a cycle', function () {
    VariantSet::make()
        ->variant('a', fn (Variant $v) => $v->basedOn('b'))
        ->variant('b', fn (Variant $v) => $v->basedOn('a'))
        ->resolve();
})->throws(InvalidArgumentException::class, 'cycle');

it('leaves the original set untouched when resolving', function () {
    $set = VariantSet::make()
        ->variant('outline', fn (Variant $v) => $v->source(Source::dir('outline')))
        ->variant('mini', fn (Variant $v) => $v->basedOn('outline'));

    $resolved = $set->resolve();

    expect($set->get('mini')->getSource())->toBeNull()
        ->and($resolved->get('mini')->getSource()?->directory())->toBe('outline');
});

it('gives a resolved variant its own fills collection', function () {
    $set = VariantSet::make()
        ->variant('duotone', fn (Variant $v) => $v->fill(new Fill('background')));

    $resolved = $set->resolve();
    $resolved->get('duotone')->fill(new Fill('foreground'));

    expect($set->get('duotone')->getFills())->toHaveCount(1)
        ->and($resolved->get('duotone')->getFills())->toHaveCount(2);
});

it('gives an inheriting variant its own copy of the fills it inherited', function () {
    $set = VariantSet::make()
        ->variant('solid', fn (Variant $v) => $v->template(Template::Solid)->fill(new Fill('background')))
        ->variant('mini', fn (Variant $v) => $v->basedOn('solid'))
        ->resolve();

    expect($set->get('mini')->getFills()->pluck('name')->all())->toBe(['background']);

    $set->get('mini')->fill(new Fill('foreground'));

    expect($set->get('mini')->getFills())->toHaveCount(2)
        ->and($set->get('solid')->getFills())->toHaveCount(1);
});

it('replaces the inherited fills when a variant defines any of its own', function () {
    $set = VariantSet::make()
        ->variant('duotone', fn (Variant $v) => $v
            ->fill(new Fill('background', matchOpacity: 0.2, opacity: 0.2))
            ->fill(new Fill('foreground'))
        )
        ->variant('accent', fn (Variant $v) => $v->basedOn('duotone')->fill(new Fill('accent', color: 'red')))
        ->resolve();

    // fills are an ordered layer stack with the catch-all last, so there is no safe
    // position to merge a parent's layers into: a variant with its own fills owns the stack
    expect($set->get('accent')->getFills()->pluck('name')->all())->toBe(['accent']);
});

it('keeps an explicitly set stroke when the parent has its stroke disabled', function () {
    $set = VariantSet::make()
        ->variant('solid', fn (Variant $v) => $v->template(Template::Solid)->stroke(false))
        ->variant('heavy', fn (Variant $v) => $v
            ->basedOn('solid')
            ->template(Template::Outline)
            ->stroke(new Stroke(2))
        )
        ->resolve();

    expect($set->get('heavy')->getStroke()?->width)->toBe(2.0);
});

it('separates vendor variants from the variants flux asks for', function () {
    $set = fluxLikeSet()
        ->variant('duotone', fn (Variant $v) => $v->template(Template::Solid));

    expect($set->fluxVariants()->keys()->all())->toBe(['outline', 'solid', 'mini', 'micro'])
        ->and($set->vendorVariants()->keys()->all())->toBe(['duotone']);
});

it('orders flux variants the way flux does regardless of definition order', function () {
    $set = VariantSet::make()
        ->variant('micro')
        ->variant('solid')
        ->variant('outline')
        ->variant('mini');

    expect($set->fluxVariants()->keys()->all())->toBe(['outline', 'solid', 'mini', 'micro']);
});

it('resolves a fallback pointing at the default variant', function () {
    $set = fluxLikeSet()->resolve();

    expect($set->fallbackFor('solid')?->getName())->toBe('outline');
});

it('resolves a fallback pointing at a named variant', function () {
    $set = VariantSet::make()
        ->variant('outline', fn (Variant $v) => $v->default())
        ->variant('solid')
        ->variant('duotone', fn (Variant $v) => $v->fallback(Fallback::variant('solid')));

    expect($set->fallbackFor('duotone')?->getName())->toBe('solid');
});

it('resolves no fallback when the variant is set to be skipped', function () {
    $set = VariantSet::make()
        ->variant('outline', fn (Variant $v) => $v->default())
        ->variant('duotone', fn (Variant $v) => $v->fallback(Fallback::skip()));

    expect($set->fallbackFor('duotone'))->toBeNull()
        ->and($set->get('duotone')->getFallback()->skips())->toBeTrue();
});

it('resolves no fallback when none was defined', function () {
    expect(fluxLikeSet()->fallbackFor('outline'))->toBeNull();
});

it('reports a variant with several fills as multitone', function () {
    $set = VariantSet::make()
        ->variant('duotone', fn (Variant $v) => $v
            ->fill(new Fill('background', matchOpacity: 0.2, opacity: 0.2))
            ->fill(new Fill('foreground'))
        );

    expect($set->get('duotone')->isMultitone())->toBeTrue()
        ->and(fluxLikeSet()->get('outline')->isMultitone())->toBeFalse();
});

it('drops the stroke on variants that are not rendered as outlines', function () {
    $set = VariantSet::make()
        ->variant('solid', fn (Variant $v) => $v->template(Template::Solid)->stroke(new Stroke(1.5)));

    expect($set->get('solid')->getStroke())->toBeNull();
});

it('drops the stroke when a vendor disables it', function () {
    $set = VariantSet::make()
        ->variant('outline', fn (Variant $v) => $v->stroke(false));

    expect($set->get('outline')->getStroke())->toBeNull();
});

it('does not re-enable a disabled stroke through inheritance', function () {
    $set = VariantSet::make()
        ->variant('outline', fn (Variant $v) => $v->stroke(new Stroke(1.5)))
        ->variant('thin', fn (Variant $v) => $v->basedOn('outline')->stroke(false))
        ->resolve();

    expect($set->get('thin')->getStroke())->toBeNull();
});
