<?php

use Ympact\FluxIcons\Tests\Vendor\Beta\Beta;
use Ympact\FluxIcons\Types\Icon;

it('loads the icon filename from a file path', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());

    expect($icon->getName())->toBe('arrow-left');
});

it('replaces file content with raw svg content', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());
    $icon->setContent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0"/></svg>', 'custom-icon');

    expect($icon->getName())->toBe('custom-icon');
});

it('reads icon size from the viewbox', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());
    $icon->process();

    expect($icon->getSize())->toBe([24, 24]);
});

it('falls back to width and height when no viewbox exists', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());
    $icon->setContent('<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><path d="M0 0"/></svg>', 'sized-icon');
    $icon->process();

    expect($icon->getSize())->toBe([20, 20]);
});

it('extracts supported svg nodes during processing', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());
    $icon->process();

    expect($icon->getPaths()->count())->toBeGreaterThan(0);
});

it('includes circle nodes in the extracted paths', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile('dots'));
    $icon->process();

    expect($icon->getPaths()->map(fn ($path) => $path->getType())->values()->toArray())->toContain('circle');
});

it('keeps the base name unchanged without a prefix or suffix', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile('arrow-left'));

    expect($icon->getBaseName())->toBe('arrow-left');
});

it('strips a configured string prefix and suffix from the base name', function () {
    $config = alphaIconConfig([
        'variants' => [
            'outline' => [
                'template' => 'outline',
                'source' => ['dir' => 'tests/vendor/epsilon/icons', 'prefix' => 'icon-', 'suffix' => '-outline'],
            ],
        ],
    ]);

    $icon = new Icon($config, 'outline', fixturePath('vendor/epsilon/icons/icon-home-outline.svg'));

    expect($icon->getBaseName())->toBe('home');
});

it('strips a configured string suffix from the base name', function () {
    $config = alphaIconConfig([
        'variants' => [
            'outline' => [
                'template' => 'outline',
                'source' => ['dir' => 'tests/vendor/beta/icons', 'prefix' => null, 'suffix' => '_24_regular'],
            ],
        ],
    ]);

    $icon = new Icon($config, 'outline', fixturePath('vendor/beta/icons/arrow-left_24_regular.svg'));

    expect($icon->getBaseName())->toBe('arrow-left');
});

it('strips a configured callable suffix from the base name', function () {
    $config = alphaIconConfig([
        'variants' => [
            'solid' => [
                'template' => 'solid',
                'source' => [
                    'dir' => 'tests/vendor/beta/icons',
                    'prefix' => null,
                    'suffix' => [Beta::class, 'sourceSolidSuffix'],
                ],
            ],
        ],
    ]);

    $icon = new Icon($config, 'solid', fixturePath('vendor/beta/icons/arrow-left_24_filled.svg'));

    expect($icon->getBaseName())->toBe('arrow-left');
});

it('applies a configured transform callback', function () {
    $config = alphaIconConfig([
        'transform' => static fn ($paths) => $paths->filter(fn ($path) => $path->getD() !== 'M0 0h24v24H0z'),
    ]);

    $icon = new Icon($config, 'outline', alphaOutlineFile());
    $icon->setContent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/><path d="M10 19l-7-7"/></svg>',
        'test-icon'
    );
    $icon->process()->transform();

    expect($icon->getD()->values()->toArray())
        ->not->toContain('M0 0h24v24H0z')
        ->toContain('M10 19l-7-7');
});

it('leaves paths unchanged when no transform callback exists', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());
    $icon->process();
    $count = $icon->getPaths()->count();
    $icon->transform();

    expect($icon->getPaths())->toHaveCount($count);
});

it('accepts path attributes without throwing', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());

    expect(fn () => $icon->process()->setPathAttributes(['data-test' => 'yes']))->not->toThrow(Throwable::class);
});

it('sets the stroke width directly', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());
    $icon->process()->setStrokeWidth(2.0);

    expect($icon->getStrokeWidth())->toBe(2.0);
});

it('uses a configured stroke width callback', function () {
    $icon = new Icon(alphaIconConfig(['stroke_width' => static fn () => 3.0]), 'outline', alphaOutlineFile());
    $icon->process()->setStrokeWidth(1.5);

    expect($icon->getStrokeWidth())->toBe(3.0);
});

it('can force the stroke width and ignore the callback', function () {
    $icon = new Icon(alphaIconConfig(['stroke_width' => static fn () => 3.0]), 'outline', alphaOutlineFile());
    $icon->process()->setStrokeWidth(1.5, true);

    expect($icon->getStrokeWidth())->toBe(1.5);
});

it('renders html with a viewbox', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());
    $html = $icon->process()->toHtml();

    expect($html)->toBeSvgMarkup(['viewBox="0 0 24 24"']);
});

it('renders an svg tag to html', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());
    $html = $icon->process()->toHtml();

    expect($html)->toBeSvgMarkup();
});

it('renders raw svg content when raw mode is enabled', function () {
    $icon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());
    $html = $icon->process()->setRaw(true)->toHtml();

    expect($html)->toBeSvgMarkup();
});

it('merges default and configured svg attributes', function () {
    $icon = new Icon(alphaIconConfig([
        'variants' => ['outline' => ['template' => 'outline', 'attributes' => ['data-vendor' => 'alpha']]],
    ]), 'outline', alphaOutlineFile());

    $icon->process();
    $icon->determineSvgAttributes();

    expect($icon->getAttributes())
        ->toHaveKey('data-vendor')
        ->toHaveKey('aria-hidden')
        ->and($icon->getAttributes()['data-vendor'])->toBe('alpha');
});
