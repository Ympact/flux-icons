<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Services\IconBuilder;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Vendors\Bootstrap;
use Ympact\FluxIcons\Vendors\Codicons;
use Ympact\FluxIcons\Vendors\Flags;
use Ympact\FluxIcons\Vendors\Flowbite;
use Ympact\FluxIcons\Vendors\Fluent;
use Ympact\FluxIcons\Vendors\Healthicons;
use Ympact\FluxIcons\Vendors\Lucide;
use Ympact\FluxIcons\Vendors\MaterialIcons;
use Ympact\FluxIcons\Vendors\MaterialSymbols;
use Ympact\FluxIcons\Vendors\Mdi;
use Ympact\FluxIcons\Vendors\Phosphor;
use Ympact\FluxIcons\Vendors\Tabler;

dataset('v1 vendors', [
    'bootstrap' => ['bootstrap', Bootstrap::class],
    'codicons' => ['codicons', Codicons::class],
    'flags' => ['flags', Flags::class],
    'flowbite' => ['flowbite', Flowbite::class],
    'fluent' => ['fluent', Fluent::class],
    'healthicons' => ['healthicons', Healthicons::class],
    'lucide' => ['lucide', Lucide::class],
    'material-icons' => ['material-icons', MaterialIcons::class],
    'material-symbols' => ['material-symbols', MaterialSymbols::class],
    'mdi' => ['mdi', Mdi::class],
    'tabler' => ['tabler', Tabler::class],
]);

it('answers every variant flux asks for', function (string $key, string $class) {
    expect((new $class)->resolvedVariants()->missingFluxVariants())->toBe([]);
})->with('v1 vendors');

it('also answers every flux variant for phosphor, which v1 never shipped', function () {
    expect((new Phosphor)->resolvedVariants()->missingFluxVariants())->toBe([]);
});

/*
 * The vendor classes replace the entries of config/flux-icons.php. Until #20 removes that
 * config, each class has to define what the v1 builder derives from its entry, or moving
 * the build onto the classes changes what gets built. The v1 builder itself is the oracle.
 */
it('defines the same variants as the v1 config entry it replaces', function (string $key, string $class) {
    config()->set('flux-icons', require dirname(__DIR__, 3).'/config/flux-icons.php');
    $builder = new IconBuilder($key);
    $anyIcon = new Icon(alphaIconConfig(), 'outline', alphaOutlineFile());
    $v1TemplateAttributes = (new ReflectionProperty(Icon::class, 'variantAttributes'))->getDefaultValue();

    $vendor = new $class;
    $variants = $vendor->resolvedVariants();
    $packagePath = 'node_modules/'.$vendor->package().'/'.$vendor->basePath();

    expect($vendor->key())->toBe($key)
        ->and($vendor->namespace())->toBe(config("flux-icons.vendors.{$key}.namespace"))
        ->and($vendor->package())->toBe(config("flux-icons.vendors.{$key}.package"));

    foreach (['outline', 'solid', 'mini', 'micro'] as $name) {
        $variant = $variants->get($name);
        $source = $variant->getSource();

        $v1Source = $builder->variantProp($name, 'source');
        $affix = function (string $part) use ($v1Source, $name) {
            $value = is_array($v1Source) ? ($v1Source[$part] ?? null) : null;

            // v1 calls an affix callback with the variant being built, which is how fluent picks a size
            return is_array($value) ? call_user_func($value, $name) : $value;
        };

        expect($source?->path($packagePath))->toBe(rtrim(is_array($v1Source) ? $v1Source['dir'] : $v1Source, '/'), "{$key} {$name}: directory")
            ->and($source->fileName('icon'))->toBe($affix('prefix').'icon'.$affix('suffix'), "{$key} {$name}: file name")
            ->and($source->hasFilter())->toBe(is_array($v1Source) && isset($v1Source['filter']), "{$key} {$name}: filter");

        // v1 reads `raw` and the stroke width from the variant named after the template
        $v1Template = $builder->variantProp($name, 'template');
        $template = $builder->variantProp($v1Template, 'raw', false) ? Template::Raw : Template::from($v1Template);
        $strokeWidth = $v1Template === 'outline' ? (float) ($builder->variantProp('outline', 'stroke_width') ?: 1.5) : null;

        expect($variant->getTemplate())->toBe($template, "{$key} {$name}: template")
            ->and($variant->getStroke()?->width)->toBe($strokeWidth, "{$key} {$name}: stroke width")
            ->and($variants->fallbackFor($name)?->getName())->toBe($builder->determineFallback($name, $anyIcon) ?: null, "{$key} {$name}: fallback");

        // the root attributes beyond the fixed ones: v1 takes its template's and the variant's own
        $v1Root = array_merge(Arr::except($v1TemplateAttributes[$v1Template], 'stroke-width'), $builder->variantProp($name, 'attributes', []));
        $root = array_merge($variant->getTemplate()->attributes(), Arr::except($variant->getStroke()?->attributes() ?? [], 'stroke-width'), $variant->getAttributes());
        ksort($v1Root);
        ksort($root);

        // v2 draws a flag as flag-icons does; v1 filled its shapes without a fill in the text colour
        if ($key === 'flags') {
            $v1Root = Arr::except($v1Root, 'fill');
        }

        expect($root)->toBe($v1Root, "{$key} {$name}: root attributes");
    }
})->with('v1 vendors');

it('publishes material icons under a namespace that differs from its key', function () {
    $vendor = new MaterialIcons;

    expect($vendor->key())->toBe('material-icons')
        ->and($vendor->namespace())->toBe('material');
});

it('tells bootstrap outline and filled icons apart by their name', function () {
    $variants = (new Bootstrap)->resolvedVariants();
    $outline = $variants->get('outline')->getSource();
    $solid = $variants->get('solid')->getSource();

    expect($outline->accepts('icons/alarm.svg'))->toBeTrue()
        ->and($outline->accepts('icons/alarm-fill.svg'))->toBeFalse()
        ->and($solid->accepts('icons/alarm-fill.svg'))->toBeTrue()
        ->and($solid->accepts('icons/alarm.svg'))->toBeFalse()
        ->and($solid->iconName('icons/alarm-fill.svg'))->toBe('alarm');
});

it('tells material symbols outline and filled icons apart by their name', function () {
    $variants = (new MaterialSymbols)->resolvedVariants();

    expect($variants->get('outline')->getSource()->accepts('outlined/home.svg'))->toBeTrue()
        ->and($variants->get('outline')->getSource()->accepts('outlined/home-fill.svg'))->toBeFalse()
        ->and($variants->get('solid')->getSource()->iconName('outlined/home-fill.svg'))->toBe('home');
});

it('treats an mdi icon as solid only when an outline sibling exists', function () {
    $directory = sys_get_temp_dir().'/flux-icons-mdi-'.Str::random(8);
    File::makeDirectory($directory);
    foreach (['abacus', 'account', 'account-outline'] as $name) {
        File::put("{$directory}/{$name}.svg", '<svg/>');
    }

    try {
        $variants = (new Mdi)->resolvedVariants();
        $outline = $variants->get('outline')->getSource();
        $solid = $variants->get('solid')->getSource();

        expect($outline->accepts("{$directory}/abacus.svg"))->toBeTrue()
            ->and($outline->accepts("{$directory}/account-outline.svg"))->toBeTrue()
            ->and($outline->accepts("{$directory}/account.svg"))->toBeFalse()
            ->and($solid->accepts("{$directory}/account.svg"))->toBeTrue()
            ->and($solid->accepts("{$directory}/abacus.svg"))->toBeFalse()
            ->and($outline->iconName('account-outline.svg'))->toBe('account');
    } finally {
        File::deleteDirectory($directory);
    }
});

it('builds each fluent variant from the drawing at the size it is shown', function () {
    $variants = (new Fluent)->resolvedVariants();

    expect($variants->get('outline')->getSource()->fileName('add'))->toBe('add_24_regular')
        ->and($variants->get('solid')->getSource()->fileName('add'))->toBe('add_24_filled')
        ->and($variants->get('mini')->getSource()->fileName('add'))->toBe('add_20_filled')
        ->and($variants->get('micro')->getSource()->fileName('add'))->toBe('add_16_filled');
});

it('builds healthicons mini and micro from the 24 pixel drawings, falling back to solid', function () {
    $variants = (new Healthicons)->resolvedVariants();

    expect($variants->get('mini')->getSource()->directory())->toBe('filled-24px/*')
        ->and($variants->get('micro')->getSource()->directory())->toBe('filled-24px/*')
        ->and($variants->fallbackFor('mini')?->getName())->toBe('solid')
        ->and($variants->fallbackFor('micro')?->getName())->toBe('solid');
});

it('publishes healthicons under usable component names', function () {
    $vendor = new Healthicons;

    expect($vendor->iconName('0'))->toBe('zero')
        ->and($vendor->iconName('Blood_A_N'))->toBe('blood-a-n')
        ->and($vendor->iconName('stethoscope'))->toBe('stethoscope');
});

it('copies flags verbatim in every variant', function () {
    $variants = (new Flags)->resolvedVariants();

    foreach (['outline', 'solid', 'mini', 'micro'] as $name) {
        expect($variants->get($name)->getTemplate())->toBe(Template::Raw);
    }
});

it('builds the 4x3 flags unless the square ones are asked for', function () {
    expect((new Flags)->resolvedVariants()->get('outline')->getSource()->directory())->toBe('4x3')
        ->and((new Flags)->withOptions(['ratio' => '1x1'])->resolvedVariants()->get('solid')->getSource()->directory())->toBe('1x1');
});

it('rejects a flag ratio the package does not ship', function () {
    (new Flags)->withOptions(['ratio' => '16x9']);
})->throws(InvalidArgumentException::class, 'not a valid value for option [ratio]');

it('builds every codicons and lucide variant from the outline files', function (string $class) {
    $variants = (new $class)->resolvedVariants();
    $outline = $variants->get('outline');

    foreach (['solid', 'mini', 'micro'] as $name) {
        expect($variants->get($name)->getSource()?->directory())->toBe($outline->getSource()->directory())
            ->and($variants->get($name)->getTemplate())->toBe($outline->getTemplate());
    }
})->with([Codicons::class, Lucide::class]);

it('keeps the adjustable stroke on every lucide variant', function () {
    $variants = (new Lucide)->resolvedVariants();

    foreach (['outline', 'solid', 'mini', 'micro'] as $name) {
        expect($variants->get($name)->getStroke()?->width)->toBe(1.5);
    }
});

it('spans the category directories of flowbite', function () {
    $variants = (new Flowbite)->resolvedVariants();

    expect($variants->get('outline')->getSource()->path('node_modules/flowbite-icons/src'))->toBe('node_modules/flowbite-icons/src/outline/*')
        ->and($variants->get('mini')->getSource()->directory())->toBe('solid/*');
});
