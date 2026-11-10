<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Contracts\Vendor;
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

function packagePath(Vendor $vendor): string
{
    return 'node_modules/'.$vendor->package().'/'.$vendor->basePath();
}

it('answers every variant flux asks for', function (string $key, string $class) {
    expect((new $class)->resolvedVariants()->missingFluxVariants())->toBe([]);
})->with('v1 vendors');

it('also answers every flux variant for phosphor, which v1 never shipped', function () {
    expect((new Phosphor)->resolvedVariants()->missingFluxVariants())->toBe([]);
});

/*
 * The vendor classes replace the entries of config/flux-icons.php. Until #20 removes that
 * config, each class has to read the same files, under the same key and namespace, as the
 * entry it replaces; otherwise moving the build onto the classes changes what gets built.
 */
it('reads the same files as the v1 config entry it replaces', function (string $key, string $class) {
    $config = (require dirname(__DIR__, 3).'/config/flux-icons.php')['vendors'][$key];
    $vendor = new $class;
    $variants = $vendor->resolvedVariants();

    expect($vendor->key())->toBe($key)
        ->and($vendor->namespace())->toBe($config['namespace'])
        ->and($vendor->package())->toBe($config['package']);

    foreach (['outline', 'solid', 'mini', 'micro'] as $name) {
        $settings = v1VariantSettings($config, $name);
        $v1Source = $settings['source'];
        $source = $variants->get($name)?->getSource();

        expect($source?->path(packagePath($vendor)))
            ->toBe(rtrim(is_array($v1Source) ? $v1Source['dir'] : $v1Source, '/'), "{$key} {$name} reads another directory");

        // v1 calls a suffix callback with the variant being built, which is how fluent picks a size
        $suffix = is_array($v1Source) ? ($v1Source['suffix'] ?? null) : null;
        $suffix = is_array($suffix) ? call_user_func($suffix, $name) : $suffix;

        expect($source->fileName('icon'))->toBe('icon'.$suffix, "{$key} {$name} names its files differently")
            ->and($source->hasFilter())->toBe(is_array($v1Source) && isset($v1Source['filter']), "{$key} {$name} filters differently");

        $template = ($settings['raw'] ?? false) ? Template::Raw : Template::from($settings['template'] ?? ($name === 'outline' ? 'outline' : 'solid'));

        expect($variants->get($name)->getTemplate())->toBe($template, "{$key} {$name} renders with another template");
    }
})->with('v1 vendors');

/**
 * A variant's settings as v1 applies them: mini and micro without a source of their own take
 * the settings of the variant they are based on, solid unless they say otherwise.
 *
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function v1VariantSettings(array $config, string $name): array
{
    $settings = $config['variants'][$name] ?? [];

    if (in_array($name, ['mini', 'micro'], true) && ! isset($settings['source'])) {
        return array_merge(v1VariantSettings($config, $settings['base'] ?? 'solid'), $settings);
    }

    return $settings;
}

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

    $variants = (new Mdi)->resolvedVariants();
    $outline = $variants->get('outline')->getSource();
    $solid = $variants->get('solid')->getSource();

    expect($outline->accepts("{$directory}/abacus.svg"))->toBeTrue()
        ->and($outline->accepts("{$directory}/account-outline.svg"))->toBeTrue()
        ->and($outline->accepts("{$directory}/account.svg"))->toBeFalse()
        ->and($solid->accepts("{$directory}/account.svg"))->toBeTrue()
        ->and($solid->accepts("{$directory}/abacus.svg"))->toBeFalse()
        ->and($outline->iconName('account-outline.svg'))->toBe('account');

    File::deleteDirectory($directory);
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

    expect($variants->get('outline')->getSource()->path(packagePath(new Flowbite)))->toBe('node_modules/flowbite-icons/src/outline/*')
        ->and($variants->get('mini')->getSource()->directory())->toBe('solid/*');
});
