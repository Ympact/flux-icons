<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Build\ComponentWriter;
use Ympact\FluxIcons\Variants\FluxVariant;
use Ympact\FluxIcons\Variants\Variant;
use Ympact\FluxIcons\Variants\VariantSet;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/flux-icons-writer-'.Str::random(8);
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

/**
 * The four Flux variants, outline the default, plus any vendor variants named.
 */
function fluxVariantSet(string ...$vendorVariants): VariantSet
{
    $set = VariantSet::make()->variant('outline', fn (Variant $variant) => $variant->default());

    foreach (['solid', 'mini', 'micro', ...$vendorVariants] as $name) {
        $set->variant($name);
    }

    return $set->resolve();
}

/**
 * @param  array<string, string>  $variants
 */
function writeComponent(string $directory, array $variants, bool $blaze = false): string
{
    $set = fluxVariantSet(...array_diff(array_keys($variants), FluxVariant::names()));

    return (string) file_get_contents((new ComponentWriter)->write($directory, 'flags', 'nl', $set, $variants, '', '', $blaze));
}

it('gives only the outer svg of a raw icon the component attributes', function () {
    $nested = '<svg viewBox="0 0 24 24"><svg viewBox="0 0 640 480"><path d="M0 0h640v480H0z"></path></svg></svg>';

    $component = writeComponent($this->directory, array_fill_keys(['outline', 'solid', 'mini', 'micro'], $nested));

    expect(substr_count($component, '{{ $attributes->class($classes) }}'))->toBe(4)
        ->and(substr_count($component, '<svg viewBox="0 0 640 480">'))->toBe(4);
});

it('writes @blaze only when the installed Flux takes it', function (bool $blaze) {
    $svg = '<svg viewBox="0 0 24 24"><path d="M0 0h24v24H0z"></path></svg>';

    $component = writeComponent($this->directory, array_fill_keys(['outline', 'solid', 'mini', 'micro'], $svg), $blaze);

    expect(str_contains($component, '@blaze'))->toBe($blaze);
})->with([true, false]);

it('writes a case and size classes for every variant, a vendor variant at the default size', function () {
    $svg = '<svg viewBox="0 0 24 24"><path d="M0 0h24v24H0z"></path></svg>';

    $component = writeComponent($this->directory, array_fill_keys(['outline', 'solid', 'mini', 'micro', 'duotone'], $svg));

    expect($component)->toContain("    <?php case ('duotone'): ?>")
        ->toContain("        'mini' => '[:where(&)]:size-5',")
        ->toContain("        'duotone' => '[:where(&)]:size-6',")
        ->toContain("in_array(\$variant, ['outline', 'solid', 'mini', 'micro', 'duotone'], true)")
        ->toContain('Variants: outline, solid, mini, micro, duotone ');
});
