<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Build\Builder;
use Ympact\FluxIcons\Events\IconsBuilt;
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
use Ympact\FluxIcons\Vendors\Tabler;

/*
 * Builds each vendor class from a miniature copy of its npm package, laid out as npm installs
 * it. What the build writes pins the files a vendor reads, how it names and filters them, where
 * it falls back, and how it draws each slot.
 */

beforeEach(function () {
    Event::fake([IconsBuilt::class]);

    $this->packages = sys_get_temp_dir().'/flux-icons-packages-'.Str::random(8);
    $this->output = sys_get_temp_dir().'/flux-icons-output-'.Str::random(8);
    $this->builder = new Builder($this->packages, $this->output);
});

afterEach(function () {
    File::deleteDirectory($this->packages);
    File::deleteDirectory($this->output);
});

/**
 * Writes the files of a miniature npm package. The `{d}` in each file's markup becomes a path of
 * that file's own, so every slot of a built component can be traced back to its source file.
 *
 * @param  string  $directory  Where the vendor reads its icons, inside node_modules.
 * @param  array<string, string>  $files  The markup of each file, by its path inside that directory.
 * @return array<string, string> Each file by the path drawn in it.
 */
function writeIconPackage(string $packages, string $directory, array $files): array
{
    $drawings = [];

    foreach ($files as $file => $markup) {
        $d = 'M'.(count($drawings) + 1).' 0h1';
        $path = "{$packages}/{$directory}/{$file}";

        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_replace('{d}', $d, $markup));
        $drawings[$d] = $file;
    }

    return $drawings;
}

/**
 * The same value for each of the four slots Flux asks for.
 *
 * @return array<string, mixed>
 */
function eachSlot(mixed $value): array
{
    return array_fill_keys(['outline', 'solid', 'mini', 'micro'], $value);
}

/**
 * The <svg> markup of each slot of a built component.
 *
 * @return array<string, string>
 */
function componentSlots(string $component): array
{
    expect($component)->toBeFile();

    preg_match_all("/case \\('(\\w+)'\\): \\?>\\s*(.*?)\\s*<\\?php break; \\?>/s", (string) file_get_contents($component), $slots);

    return array_combine($slots[1], $slots[2]);
}

/**
 * The file each slot was drawn from, or every file it shows when that is not exactly one.
 *
 * @param  array<string, string>  $drawings  As writeIconPackage() returns them.
 * @return array<string, string|list<string>>
 */
function slotSources(string $component, array $drawings): array
{
    return array_map(function (string $slot) use ($drawings) {
        preg_match_all('/\bd="([^"]*)"/', $slot, $paths);
        $files = array_values(array_unique(array_intersect_key($drawings, array_flip($paths[1]))));

        return count($files) === 1 ? $files[0] : $files;
    }, componentSlots($component));
}

/**
 * The attributes of each slot's outer <svg>, by name, leaving out the four every built icon
 * carries for Flux.
 *
 * @return array<string, array<string, string>>
 */
function slotRoots(string $component): array
{
    return array_map(function (string $slot) {
        preg_match('/^<svg \{\{ \$attributes->class\(\$classes\) \}\}([^>]*)>/', $slot, $root);
        $attributes = svgAttributes($root[1] ?? '');
        unset($attributes['xmlns'], $attributes['data-flux-icon'], $attributes['aria-hidden'], $attributes['data-slot']);

        return $attributes;
    }, componentSlots($component));
}

/**
 * The attributes of each <path> in each slot other than its `d`, in drawing order.
 *
 * @return array<string, list<array<string, string>>>
 */
function slotPathAttributes(string $component): array
{
    return array_map(function (string $slot) {
        preg_match_all('/<path\s([^>]*)>/', $slot, $paths);

        return array_map(fn (string $path) => Arr::except(svgAttributes($path), 'd'), $paths[1]);
    }, componentSlots($component));
}

/**
 * The attributes written in a tag's markup, by name.
 *
 * @return array<string, string>
 */
function svgAttributes(string $markup): array
{
    preg_match_all('/([\w:-]+)="([^"]*)"/', $markup, $attributes);
    $attributes = array_combine($attributes[1], $attributes[2]);
    ksort($attributes);

    return $attributes;
}

describe('Bootstrap', function () {
    beforeEach(function () {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi" viewBox="0 0 16 16"><path d="{d}"/></svg>';

        $this->drawings = writeIconPackage($this->packages, 'bootstrap-icons/icons', [
            'alarm.svg' => $svg,
            'alarm-fill.svg' => $svg,
            'bag.svg' => $svg,
            'heart-fill.svg' => $svg,
        ]);
    });

    it('builds an icon from its -fill drawing, or from its outline when it has none', function () {
        $result = $this->builder->build(new Bootstrap);

        expect($result->built)->toBe(['alarm', 'bag'])
            ->and($result->skipped)->toBe([])
            ->and(slotSources("{$this->output}/bootstrap/alarm.blade.php", $this->drawings))->toBe([
                'outline' => 'alarm.svg',
                'solid' => 'alarm-fill.svg',
                'mini' => 'alarm-fill.svg',
                'micro' => 'alarm-fill.svg',
            ])
            ->and(slotSources("{$this->output}/bootstrap/bag.blade.php", $this->drawings))->toBe(eachSlot('bag.svg'));
    });

    it('never builds a -fill drawing as an icon of its own', function () {
        $result = $this->builder->build(new Bootstrap, ['alarm-fill', 'heart-fill']);

        expect($result->built)->toBe([])
            ->and($result->missing)->toBe(['alarm-fill', 'heart-fill']);
    });

    it('draws every slot as a filled shape', function () {
        $this->builder->build(new Bootstrap);

        expect(slotRoots("{$this->output}/bootstrap/alarm.blade.php"))->toBe(eachSlot(['fill' => 'currentColor', 'viewBox' => '0 0 16 16']))
            ->and(slotRoots("{$this->output}/bootstrap/bag.blade.php"))->toBe(eachSlot(['fill' => 'currentColor', 'viewBox' => '0 0 16 16']));
    });
});

describe('Codicons', function () {
    beforeEach(function () {
        $svg = '<svg width="16" height="16" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="{d}"/></svg>';

        $this->drawings = writeIconPackage($this->packages, '@vscode/codicons/src/icons', [
            'account.svg' => $svg,
            'add.svg' => $svg,
        ]);
    });

    it('builds every slot from the one drawing codicons ships', function () {
        $result = $this->builder->build(new Codicons);

        expect($result->built)->toBe(['account', 'add'])
            ->and($result->skipped)->toBe([])
            ->and(slotSources("{$this->output}/codicons/account.blade.php", $this->drawings))->toBe(eachSlot('account.svg'))
            ->and(slotSources("{$this->output}/codicons/add.blade.php", $this->drawings))->toBe(eachSlot('add.svg'));
    });

    it('draws every slot as a filled shape', function () {
        $this->builder->build(new Codicons);

        expect(slotRoots("{$this->output}/codicons/add.blade.php"))->toBe(eachSlot(['fill' => 'currentColor', 'viewBox' => '0 0 16 16']));
    });
});

describe('Flags', function () {
    beforeEach(function () {
        $this->drawings = writeIconPackage($this->packages, 'flag-icons/flags', [
            '1x1/nl.svg' => '<svg xmlns="http://www.w3.org/2000/svg" id="flag-icons-nl" viewBox="0 0 512 512"><path fill="#ae1c28" d="{d}"/></svg>',
            '4x3/be.svg' => '<svg xmlns="http://www.w3.org/2000/svg" id="flag-icons-be" viewBox="0 0 640 480"><path fill="#ffd90c" d="{d}"/></svg>',
            '4x3/nl.svg' => '<svg xmlns="http://www.w3.org/2000/svg" id="flag-icons-nl" viewBox="0 0 640 480"><path fill="#ae1c28" d="{d}"/></svg>',
        ]);
    });

    it('builds the 4x3 flags unless another ratio is asked for', function () {
        $result = $this->builder->build(new Flags);

        expect($result->built)->toBe(['be', 'nl'])
            ->and($result->skipped)->toBe([])
            ->and(slotSources("{$this->output}/flags/be.blade.php", $this->drawings))->toBe(eachSlot('4x3/be.svg'))
            ->and(slotSources("{$this->output}/flags/nl.blade.php", $this->drawings))->toBe(eachSlot('4x3/nl.svg'))
            ->and(slotRoots("{$this->output}/flags/nl.blade.php"))->toBe(eachSlot(['viewBox' => '0 0 640 480']));
    });

    it('builds the square flags when the 1x1 ratio is asked for', function () {
        $result = $this->builder->build((new Flags)->withOptions(['ratio' => '1x1']));

        expect($result->built)->toBe(['nl'])
            ->and(slotSources("{$this->output}/flags/nl.blade.php", $this->drawings))->toBe(eachSlot('1x1/nl.svg'))
            ->and(slotRoots("{$this->output}/flags/nl.blade.php"))->toBe(eachSlot(['viewBox' => '0 0 512 512']));
    });

    it('copies a flag in its own colours rather than filling it with the text colour', function () {
        $this->builder->build(new Flags, ['nl']);

        foreach (componentSlots("{$this->output}/flags/nl.blade.php") as $slot) {
            expect($slot)->toContain('<svg id="flag-icons-nl"')
                ->and($slot)->toContain('fill="#ae1c28"')
                ->and($slot)->not->toContain('currentColor');
        }
    });
});

describe('Flowbite', function () {
    beforeEach(function () {
        $outline = '<svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{d}"/></svg>';
        $solid = '<svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="{d}" clip-rule="evenodd"/></svg>';

        $this->drawings = writeIconPackage($this->packages, 'flowbite-icons/src', [
            'outline/arrows/arrow-left.svg' => $outline,
            'outline/general/home.svg' => $outline,
            'solid/general/home.svg' => $solid,
        ]);
    });

    it('builds icons from every category directory, falling back to the outline', function () {
        $result = $this->builder->build(new Flowbite);

        expect($result->built)->toBe(['arrow-left', 'home'])
            ->and($result->skipped)->toBe([])
            ->and(slotSources("{$this->output}/flowbite/home.blade.php", $this->drawings))->toBe([
                'outline' => 'outline/general/home.svg',
                'solid' => 'solid/general/home.svg',
                'mini' => 'solid/general/home.svg',
                'micro' => 'solid/general/home.svg',
            ])
            ->and(slotSources("{$this->output}/flowbite/arrow-left.blade.php", $this->drawings))->toBe(eachSlot('outline/arrows/arrow-left.svg'));
    });

    it('strokes the outline without setting caps or joins, which the paths carry', function () {
        $this->builder->build(new Flowbite);

        $outline = ['fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5', 'viewBox' => '0 0 24 24'];
        $solid = ['fill' => 'currentColor', 'viewBox' => '0 0 24 24'];

        expect(slotRoots("{$this->output}/flowbite/home.blade.php"))->toBe(['outline' => $outline, 'solid' => $solid, 'mini' => $solid, 'micro' => $solid])
            ->and(slotRoots("{$this->output}/flowbite/arrow-left.blade.php"))->toBe(eachSlot($outline));
    });
});

describe('Fluent', function () {
    beforeEach(function () {
        $svg = fn (int $size) => "<svg width=\"{$size}\" height=\"{$size}\" fill=\"none\" viewBox=\"0 0 {$size} {$size}\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"{d}\" fill=\"currentColor\"/></svg>";

        $this->drawings = writeIconPackage($this->packages, '@fluentui/svg-icons/icons', [
            'add_16_filled.svg' => $svg(16),
            'add_16_regular.svg' => $svg(16),
            'add_20_filled.svg' => $svg(20),
            'add_20_regular.svg' => $svg(20),
            'add_24_filled.svg' => $svg(24),
            'add_24_regular.svg' => $svg(24),
            'alert_20_regular.svg' => $svg(20),
            'home_24_filled.svg' => $svg(24),
            'home_24_regular.svg' => $svg(24),
        ]);
    });

    it('builds each slot from the drawing at the size flux shows it', function () {
        $this->builder->build(new Fluent);

        expect(slotSources("{$this->output}/fluent/add.blade.php", $this->drawings))->toBe([
            'outline' => 'add_24_regular.svg',
            'solid' => 'add_24_filled.svg',
            'mini' => 'add_20_filled.svg',
            'micro' => 'add_16_filled.svg',
        ])
            ->and(slotRoots("{$this->output}/fluent/add.blade.php"))->toBe([
                'outline' => ['fill' => 'currentColor', 'viewBox' => '0 0 24 24'],
                'solid' => ['fill' => 'currentColor', 'viewBox' => '0 0 24 24'],
                'mini' => ['fill' => 'currentColor', 'viewBox' => '0 0 20 20'],
                'micro' => ['fill' => 'currentColor', 'viewBox' => '0 0 16 16'],
            ]);
    });

    it('lists only the icons drawn at 24 pixels', function () {
        $result = $this->builder->build(new Fluent, ['add', 'home', 'alert']);

        expect($result->built)->toBe(['add', 'home'])
            ->and($result->missing)->toBe(['alert']);
    });

    // mini and micro inherit solid's fallback, which is the default variant: the regular drawing
    it('falls back to the 24 pixel regular drawing for a size with no filled drawing', function () {
        $result = $this->builder->build(new Fluent);

        expect($result->skipped)->toBe([])
            ->and(slotSources("{$this->output}/fluent/home.blade.php", $this->drawings))->toBe([
                'outline' => 'home_24_regular.svg',
                'solid' => 'home_24_filled.svg',
                'mini' => 'home_24_regular.svg',
                'micro' => 'home_24_regular.svg',
            ]);
    });
});

describe('Healthicons', function () {
    beforeEach(function () {
        $svg = fn (int $size) => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$size}\" height=\"{$size}\" fill=\"none\" viewBox=\"0 0 {$size} {$size}\"><path fill=\"currentColor\" d=\"{d}\"/></svg>";

        $this->drawings = writeIconPackage($this->packages, 'healthicons/public/icons/svg', [
            'outline/blood/Blood_A_N.svg' => $svg(48),
            'filled/blood/Blood_A_N.svg' => $svg(48),
            'outline/devices/ecg.svg' => $svg(48),
            'outline/devices/stethoscope.svg' => $svg(48),
            'filled/devices/stethoscope.svg' => $svg(48),
            'filled-24px/devices/stethoscope.svg' => $svg(24),
            'outline/symbols/0.svg' => $svg(48),
            'filled/symbols/0.svg' => $svg(48),
        ]);
    });

    it('builds mini and micro from the 24 pixel drawing, or else from the filled one', function () {
        $this->builder->build(new Healthicons);

        expect(slotSources("{$this->output}/healthicons/stethoscope.blade.php", $this->drawings))->toBe([
            'outline' => 'outline/devices/stethoscope.svg',
            'solid' => 'filled/devices/stethoscope.svg',
            'mini' => 'filled-24px/devices/stethoscope.svg',
            'micro' => 'filled-24px/devices/stethoscope.svg',
        ])
            ->and(slotRoots("{$this->output}/healthicons/stethoscope.blade.php"))->toBe([
                'outline' => ['fill' => 'currentColor', 'viewBox' => '0 0 48 48'],
                'solid' => ['fill' => 'currentColor', 'viewBox' => '0 0 48 48'],
                'mini' => ['fill' => 'currentColor', 'viewBox' => '0 0 24 24'],
                'micro' => ['fill' => 'currentColor', 'viewBox' => '0 0 24 24'],
            ])
            ->and(slotSources("{$this->output}/healthicons/blood-a-n.blade.php", $this->drawings))->toBe([
                'outline' => 'outline/blood/Blood_A_N.svg',
                'solid' => 'filled/blood/Blood_A_N.svg',
                'mini' => 'filled/blood/Blood_A_N.svg',
                'micro' => 'filled/blood/Blood_A_N.svg',
            ]);
    });

    it('publishes icons under usable component names', function () {
        $result = $this->builder->build(new Healthicons);

        expect($result->built)->toBe(['blood-a-n', 'stethoscope', 'zero'])
            ->and(slotSources("{$this->output}/healthicons/zero.blade.php", $this->drawings))->toBe([
                'outline' => 'outline/symbols/0.svg',
                'solid' => 'filled/symbols/0.svg',
                'mini' => 'filled/symbols/0.svg',
                'micro' => 'filled/symbols/0.svg',
            ]);
    });

    it('selects icons by the name they are published under', function () {
        $result = $this->builder->build(new Healthicons, ['zero', 'blood-a-n']);

        expect($result->built)->toBe(['blood-a-n', 'zero'])
            ->and($result->missing)->toBe([]);
    });

    // mini falls back to solid, and a fallback is followed once, so it never reaches the outline
    it('skips an icon that has no filled drawing', function () {
        $result = $this->builder->build(new Healthicons);

        expect($result->skipped)->toBe(['ecg' => 'no source for its mini variant'])
            ->and("{$this->output}/healthicons/ecg.blade.php")->not->toBeFile();
    });
});

describe('Lucide', function () {
    beforeEach(function () {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide"><path d="{d}"/></svg>';

        $this->drawings = writeIconPackage($this->packages, 'lucide-static/icons', [
            'bell.svg' => $svg,
            'house.svg' => $svg,
        ]);
    });

    it('builds every slot from the one stroked drawing lucide ships', function () {
        $result = $this->builder->build(new Lucide);

        expect($result->built)->toBe(['bell', 'house'])
            ->and($result->skipped)->toBe([])
            ->and(slotSources("{$this->output}/lucide/bell.blade.php", $this->drawings))->toBe(eachSlot('bell.svg'))
            ->and(slotSources("{$this->output}/lucide/house.blade.php", $this->drawings))->toBe(eachSlot('house.svg'));
    });

    it('strokes every slot at 1.5 with the round caps and joins lucide draws', function () {
        $this->builder->build(new Lucide);

        expect(slotRoots("{$this->output}/lucide/house.blade.php"))->toBe(eachSlot([
            'fill' => 'none',
            'stroke' => 'currentColor',
            'stroke-linecap' => 'round',
            'stroke-linejoin' => 'round',
            'stroke-width' => '1.5',
            'viewBox' => '0 0 24 24',
        ]));
    });
});

describe('MaterialIcons', function () {
    beforeEach(function () {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="{d}"/></svg>';

        $this->drawings = writeIconPackage($this->packages, '@material-design-icons/svg', [
            'filled/home.svg' => $svg,
            'outlined/home.svg' => $svg,
            'outlined/search.svg' => $svg,
            'round/home.svg' => $svg,
        ]);
    });

    it('publishes under the material namespace rather than its key', function () {
        $this->builder->build(new MaterialIcons);

        expect("{$this->output}/material/home.blade.php")->toBeFile()
            ->and("{$this->output}/material-icons")->not->toBeDirectory()
            ->and(file_get_contents("{$this->output}/material/home.blade.php"))->toContain('<flux:icon.material.home />');

        Event::assertDispatched(IconsBuilt::class, fn (IconsBuilt $event) => $event->vendor === 'material-icons'
            && $event->namespace === 'material');
    });

    it('builds an icon from the outlined and filled styles, falling back to the outline', function () {
        $result = $this->builder->build(new MaterialIcons);

        expect($result->built)->toBe(['home', 'search'])
            ->and($result->skipped)->toBe([])
            ->and(slotSources("{$this->output}/material/home.blade.php", $this->drawings))->toBe([
                'outline' => 'outlined/home.svg',
                'solid' => 'filled/home.svg',
                'mini' => 'filled/home.svg',
                'micro' => 'filled/home.svg',
            ])
            ->and(slotSources("{$this->output}/material/search.blade.php", $this->drawings))->toBe(eachSlot('outlined/search.svg'));
    });

    it('draws both styles as filled shapes, with round caps on the outline', function () {
        $this->builder->build(new MaterialIcons);

        $outline = ['fill' => 'currentColor', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'viewBox' => '0 0 24 24'];
        $solid = ['fill' => 'currentColor', 'viewBox' => '0 0 24 24'];

        expect(slotRoots("{$this->output}/material/home.blade.php"))->toBe(['outline' => $outline, 'solid' => $solid, 'mini' => $solid, 'micro' => $solid])
            ->and(slotRoots("{$this->output}/material/search.blade.php"))->toBe(eachSlot($outline));
    });
});

describe('MaterialSymbols', function () {
    beforeEach(function () {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" height="48" viewBox="0 -960 960 960" width="48"><path d="{d}"/></svg>';

        $this->drawings = writeIconPackage($this->packages, '@material-symbols/svg-300', [
            'outlined/home.svg' => $svg,
            'outlined/home-fill.svg' => $svg,
            'outlined/search.svg' => $svg,
            'rounded/home.svg' => $svg,
        ]);
    });

    it('builds an icon from its -fill drawing, or from its outline when it has none', function () {
        $result = $this->builder->build(new MaterialSymbols);

        expect($result->built)->toBe(['home', 'search'])
            ->and($result->skipped)->toBe([])
            ->and(slotSources("{$this->output}/material-symbols/home.blade.php", $this->drawings))->toBe([
                'outline' => 'outlined/home.svg',
                'solid' => 'outlined/home-fill.svg',
                'mini' => 'outlined/home-fill.svg',
                'micro' => 'outlined/home-fill.svg',
            ])
            ->and(slotSources("{$this->output}/material-symbols/search.blade.php", $this->drawings))->toBe(eachSlot('outlined/search.svg'));
    });

    it('never builds a -fill drawing as an icon of its own', function () {
        $result = $this->builder->build(new MaterialSymbols, ['home-fill']);

        expect($result->built)->toBe([])
            ->and($result->missing)->toBe(['home-fill']);
    });

    it('draws every slot as a filled shape on the symbols canvas', function () {
        $this->builder->build(new MaterialSymbols);

        expect(slotRoots("{$this->output}/material-symbols/home.blade.php"))->toBe(eachSlot(['fill' => 'currentColor', 'viewBox' => '0 -960 960 960']));
    });
});

describe('Mdi', function () {
    beforeEach(function () {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="{d}"/></svg>';

        $this->drawings = writeIconPackage($this->packages, '@mdi/svg/svg', [
            'abacus.svg' => $svg,
            'account.svg' => $svg,
            'account-outline.svg' => $svg,
            'bell-outline.svg' => $svg,
        ]);
    });

    it('builds an icon from its -outline sibling and its own drawing', function () {
        $result = $this->builder->build(new Mdi);

        expect($result->built)->toBe(['abacus', 'account', 'bell'])
            ->and($result->skipped)->toBe([])
            ->and(slotSources("{$this->output}/mdi/account.blade.php", $this->drawings))->toBe([
                'outline' => 'account-outline.svg',
                'solid' => 'account.svg',
                'mini' => 'account.svg',
                'micro' => 'account.svg',
            ]);
    });

    it('builds an icon with one drawing from that drawing in every slot', function () {
        $this->builder->build(new Mdi);

        expect(slotSources("{$this->output}/mdi/abacus.blade.php", $this->drawings))->toBe(eachSlot('abacus.svg'))
            ->and(slotSources("{$this->output}/mdi/bell.blade.php", $this->drawings))->toBe(eachSlot('bell-outline.svg'));
    });

    it('draws every slot as a filled shape', function () {
        $this->builder->build(new Mdi);

        expect(slotRoots("{$this->output}/mdi/account.blade.php"))->toBe(eachSlot(['fill' => 'currentColor', 'viewBox' => '0 0 24 24']));
    });
});

describe('Tabler', function () {
    beforeEach(function () {
        $box = '<path stroke="none" d="M0 0h24v24H0z" fill="none"/>';
        $outline = fn (string $paths = '') => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler">'.$box.$paths.'<path d="{d}"/></svg>';
        $filled = fn (string $paths = '') => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="icon icon-tabler">'.$box.$paths.'<path d="{d}"/></svg>';

        $this->drawings = writeIconPackage($this->packages, '@tabler/icons/icons', [
            'outline/arrow-autofit-left.svg' => $outline(),
            'outline/arrow-left.svg' => $outline(),
            'outline/bell.svg' => $outline(),
            'outline/confetti.svg' => $outline('<path d="M4 5h2"/><path d="M5 4v2"/><path d="M11.5 4l-.5 2"/>'),
            'outline/dots.svg' => $outline('<path d="M5 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/>'),
            'outline/home.svg' => $outline(),
            'outline/refresh.svg' => $outline(),
            'filled/arrow-autofit-left.svg' => $filled(),
            'filled/confetti.svg' => $filled('<path d="M3 5h1"/><path d="M7 4h1"/><path d="M15 9h1"/>'),
            'filled/home.svg' => $filled(),
        ]);
    });

    it('builds an icon from its filled drawing, or from its outline when it has none', function () {
        $result = $this->builder->build(new Tabler);

        expect($result->built)->toBe(['arrow-autofit-left', 'arrow-left', 'bell', 'confetti', 'dots', 'home', 'refresh'])
            ->and($result->skipped)->toBe([]);

        foreach (['arrow-autofit-left', 'confetti', 'home'] as $icon) {
            expect(slotSources("{$this->output}/tabler/{$icon}.blade.php", $this->drawings))->toBe([
                'outline' => "outline/{$icon}.svg",
                'solid' => "filled/{$icon}.svg",
                'mini' => "filled/{$icon}.svg",
                'micro' => "filled/{$icon}.svg",
            ]);
        }

        foreach (['arrow-left', 'bell', 'dots', 'refresh'] as $icon) {
            expect(slotSources("{$this->output}/tabler/{$icon}.blade.php", $this->drawings))->toBe(eachSlot("outline/{$icon}.svg"));
        }
    });

    it('removes the bounding box tabler draws around every icon', function () {
        $result = $this->builder->build(new Tabler);

        foreach ($result->built as $icon) {
            expect(file_get_contents("{$this->output}/tabler/{$icon}.blade.php"))->not->toContain('M0 0h24v24H0z');
        }
    });

    it('strokes the outline and fills the filled drawing even-odd', function () {
        $this->builder->build(new Tabler);

        $outline = [
            'fill' => 'none',
            'stroke' => 'currentColor',
            'stroke-linecap' => 'round',
            'stroke-linejoin' => 'round',
            'stroke-width' => '1.5',
            'viewBox' => '0 0 24 24',
        ];
        $solid = ['clip-rule' => 'evenodd', 'fill' => 'currentColor', 'fill-rule' => 'evenodd', 'viewBox' => '0 0 24 24'];

        expect(slotRoots("{$this->output}/tabler/home.blade.php"))->toBe(['outline' => $outline, 'solid' => $solid, 'mini' => $solid, 'micro' => $solid])
            ->and(slotRoots("{$this->output}/tabler/bell.blade.php"))->toBe(eachSlot($outline));
    });

    it('strokes all but the last two confetti paths in a filled slot, and none in the outline', function () {
        $this->builder->build(new Tabler);

        $stroked = ['stroke' => 'currentColor', 'stroke-width' => '1.5'];

        expect(slotPathAttributes("{$this->output}/tabler/confetti.blade.php"))->toBe([
            'outline' => [[], [], [], []],
            'solid' => [$stroked, $stroked, [], []],
            'mini' => [$stroked, $stroked, [], []],
            'micro' => [$stroked, $stroked, [], []],
        ]);
    });

    it('draws a stroke at 2 where 1.5 would read too light', function (string $icon, array $widths) {
        $this->builder->build(new Tabler);

        $drawn = array_map(fn (array $root) => $root['stroke-width'] ?? null, slotRoots("{$this->output}/tabler/{$icon}.blade.php"));

        expect($drawn)->toBe($widths);
    })->with([
        'a plain icon' => ['bell', ['outline' => '1.5', 'solid' => '1.5', 'mini' => '1.5', 'micro' => '1.5']],
        'small circles, in any slot' => ['dots', ['outline' => '2', 'solid' => '2', 'mini' => '2', 'micro' => '2']],
        'refresh, where a filled slot falls back' => ['refresh', ['outline' => '1.5', 'solid' => '2', 'mini' => '2', 'micro' => '2']],
        'an arrow, where a filled slot falls back' => ['arrow-left', ['outline' => '1.5', 'solid' => '2', 'mini' => '2', 'micro' => '2']],
        'an arrow with a filled drawing, which has no stroke' => ['arrow-autofit-left', ['outline' => '1.5', 'solid' => null, 'mini' => null, 'micro' => null]],
    ]);
});
