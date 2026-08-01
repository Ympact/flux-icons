<?php

use Ympact\FluxIcons\Sources\Source;

it('resolves a directory against the package path', function () {
    expect(Source::dir('icons/outline')->path('node_modules/@tabler/icons'))
        ->toBe('node_modules/@tabler/icons/icons/outline');
});

it('resolves an empty directory to the package path itself', function () {
    expect(Source::dir()->path('node_modules/lucide-static'))
        ->toBe('node_modules/lucide-static');
});

it('builds a file name from a prefix and a suffix', function () {
    $source = Source::dir('icons')->prefix('icon-')->suffix('-24');

    expect($source->fileName('home'))->toBe('icon-home-24');
});

it('builds a plain file name when no prefix or suffix is set', function () {
    expect(Source::dir('icons')->fileName('home'))->toBe('home');
});

it('strips the prefix and suffix to recover the icon name', function () {
    $source = Source::dir('icons')->prefix('icon-')->suffix('-24');

    expect($source->iconName('icon-home-24.svg'))->toBe('home');
});

it('recovers the icon name from a full path', function () {
    $source = Source::dir('assets/duotone')->suffix('-duotone');

    expect($source->iconName('/tmp/assets/duotone/address-book-duotone.svg'))->toBe('address-book');
});

it('only strips the suffix from the end of an icon name', function () {
    $source = Source::dir('icons')->suffix('-fill');

    expect($source->iconName('fill-drip-fill.svg'))->toBe('fill-drip');
});

it('globs on the prefix and suffix when no filter narrows the directory', function () {
    expect(Source::dir('icons')->suffix('-fill')->pattern())->toBe('*-fill.svg');
});

it('globs on everything when a filter decides which files belong to the variant', function () {
    $source = Source::dir('icons')
        ->suffix('-fill')
        ->filter(fn (string $file) => str_contains($file, '-fill'));

    expect($source->pattern())->toBe('*.svg')
        ->and($source->hasFilter())->toBeTrue();
});

it('accepts every file when no filter is set', function () {
    expect(Source::dir('icons')->accepts('anything.svg'))->toBeTrue();
});

it('applies the filter to decide whether a file belongs to the variant', function () {
    $source = Source::dir('icons')->filter(fn (string $file) => ! str_contains($file, '-fill'));

    expect($source->accepts('home.svg'))->toBeTrue()
        ->and($source->accepts('home-fill.svg'))->toBeFalse();
});
