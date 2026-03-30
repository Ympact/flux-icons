<?php

use function Ympact\FluxIcons\arrayMergeRecursive;

it('merges flat arrays', function () {
    $result = arrayMergeRecursive(['a' => 1, 'b' => 2], ['b' => 99, 'c' => 3]);

    expect($result)->toBe(['a' => 1, 'b' => 99, 'c' => 3]);
});

it('merges nested arrays recursively', function () {
    $result = arrayMergeRecursive(
        ['key' => ['nested' => 'old', 'other' => 'stays']],
        ['key' => ['nested' => 'new']]
    );

    expect($result)->toBe(['key' => ['nested' => 'new', 'other' => 'stays']]);
});

it('removes top-level keys overridden with null', function () {
    $result = arrayMergeRecursive(['a' => 1, 'b' => 2, 'c' => 3], ['b' => null]);

    expect($result)
        ->not->toHaveKey('b')
        ->and($result['a'])->toBe(1)
        ->and($result['c'])->toBe(3);
});

it('preserves nested null values with the current implementation', function () {
    $result = arrayMergeRecursive(
        ['attrs' => ['stroke' => 'currentColor', 'fill' => 'none']],
        ['attrs' => ['fill' => null]]
    );

    expect($result['attrs'])
        ->toHaveKey('fill')
        ->and($result['attrs']['fill'])->toBeNull()
        ->and($result['attrs']['stroke'])->toBe('currentColor');
});

it('merges multiple arrays', function () {
    $result = arrayMergeRecursive(['x' => 1], ['y' => 2], ['z' => 3]);

    expect($result)->toBe(['x' => 1, 'y' => 2, 'z' => 3]);
});
