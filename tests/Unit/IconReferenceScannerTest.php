<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Services\IconReference;
use Ympact\FluxIcons\Services\IconReferenceScanner;

/**
 * Write a Blade template into a throwaway directory and scan it.
 */
function scanTemplate(string $contents, array $namespaces = ['tabler']): Collection
{
    $directory = sys_get_temp_dir().'/flux-icons-scan-'.Str::random(8);
    mkdir($directory, 0777, true);
    file_put_contents($directory.'/page.blade.php', $contents);

    $references = (new IconReferenceScanner($namespaces))->scan([$directory]);

    unlink($directory.'/page.blade.php');
    rmdir($directory);

    return $references;
}

function scannedNames(string $contents, array $namespaces = ['tabler']): array
{
    return scanTemplate($contents, $namespaces)
        ->map(fn (IconReference $reference) => $reference->name())
        ->all();
}

it('finds an icon named in an attribute', function () {
    expect(scannedNames('<flux:button icon="tabler.chart-line" />'))
        ->toBe(['tabler.chart-line']);
});

it('finds an icon named in a tag', function () {
    expect(scannedNames('<flux:icon.tabler.arrow-right />'))
        ->toBe(['tabler.arrow-right']);
});

it('finds an icon behind a bound attribute', function () {
    expect(scannedNames('<flux:card :icon="$open ? \'tabler.lock\' : \'tabler.world\'" />'))
        ->toBe(['tabler.lock', 'tabler.world']);
});

it('finds icons in any attribute, not only ones called icon', function () {
    // Seen in the wild as variant="tabler.subtask"; it resolves the same way and fails
    // the same way.
    expect(scannedNames('<flux:button variant="tabler.subtask" />'))
        ->toBe(['tabler.subtask']);
});

it('ignores icons of vendors it was not asked about', function () {
    expect(scannedNames('<flux:icon.mdi.home /><flux:icon.tabler.home />'))
        ->toBe(['tabler.home']);
});

it('ignores a bare icon name, which belongs to flux and not to us', function () {
    expect(scannedNames('<flux:button icon="magnifying-glass" />'))->toBe([]);
});

it('ignores a credit url that happens to start with the vendor name', function () {
    expect(scannedNames('{{-- Credit: https://tabler.io/icons --}}'))->toBe([]);
});

it('reports a doubled namespace rather than silently skipping it', function () {
    $reference = scanTemplate('<flux:link icon="tabler.tabler.user-scan" />')->first();

    expect($reference->name())->toBe('tabler.tabler.user-scan')
        ->and($reference->isMalformed())->toBeTrue()
        ->and($reference->exists())->toBeFalse();
});

it('records the line each reference was found on', function () {
    $references = scanTemplate("<div>\n    <flux:icon.tabler.star />\n</div>");

    expect($references->first()->line)->toBe(2);
});

it('finds every occurrence, including repeats on one line', function () {
    expect(scannedNames('<flux:icon.tabler.star /><flux:icon.tabler.star />'))
        ->toBe(['tabler.star', 'tabler.star']);
});

it('scans nothing when no namespaces are given', function () {
    expect(scannedNames('<flux:icon.tabler.star />', []))->toBe([]);
});

it('skips directories that do not exist', function () {
    expect((new IconReferenceScanner(['tabler']))->scan(['/no/such/place']))->toHaveCount(0);
});

/**
 * Write files into a throwaway directory, scan it, and return the names found per file.
 *
 * @param  array<string, string>  $files  Contents by path inside the directory.
 * @return array<string, list<string>>
 */
function scanFiles(array $files, ?string $iconsPath = null, ?string $directory = null, array $namespaces = ['tabler']): array
{
    $directory ??= sys_get_temp_dir().'/flux-icons-scan-'.Str::random(8);

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$directory}/{$path}"));
        File::put("{$directory}/{$path}", $contents);
    }

    $found = [];

    foreach ((new IconReferenceScanner($namespaces, $iconsPath ? "{$directory}/{$iconsPath}" : null))->scan([$directory]) as $reference) {
        $found[Str::after($reference->file, $directory.'/')][] = $reference->name();
    }

    File::deleteDirectory($directory);
    ksort($found);

    return $found;
}

it('finds an icon named in a php string, such as an enum keeps', function () {
    $enum = <<<'PHP'
        <?php

        enum Status: string
        {
            case Open = 'open';

            public function icon(): string
            {
                return match ($this) {
                    self::Open => 'tabler.lock-open',
                };
            }
        }
        PHP;

    expect(scanFiles(['app/Enums/Status.php' => $enum]))->toBe(['app/Enums/Status.php' => ['tabler.lock-open']]);
});

it('ignores a name in a php comment, such as a disabled enum case', function () {
    $enum = <<<'PHP'
        <?php

        // 'tabler.line-comment'
        # 'tabler.hash-comment'
        /**
         * Draws tabler.doc-comment
         */
        $icons = [
            'tabler.kept',
            /* 'tabler.disabled-case',
               'tabler.disabled-too', */
        ];
        PHP;

    expect(scanFiles(['menu.php' => $enum]))->toBe(['menu.php' => ['tabler.kept']]);
});

it('ignores a name in a blade comment, a php block comment and an @php comment', function () {
    $template = <<<'BLADE'
        {{-- <flux:icon.tabler.commented-out />
             icons, e.g. 'tabler.documented' --}}
        <?php /* 'tabler.in-php-block' */ $icon = 'tabler.from-php-tag'; ?>
        @php
            // 'tabler.in-at-php'
            $other = 'tabler.from-at-php';
        @endphp
        <flux:icon.tabler.kept />
        BLADE;

    expect(scanFiles(['page.blade.php' => $template]))
        ->toBe(['page.blade.php' => ['tabler.from-php-tag', 'tabler.from-at-php', 'tabler.kept']]);
});

it('finds a name in an html comment, which blade still renders', function () {
    expect(scannedNames('<!-- <flux:icon.tabler.rendered /> -->'))->toBe(['tabler.rendered']);
});

it('counts lines through a blanked comment', function () {
    $references = scanTemplate("{{-- one\n two\n three --}}\n<flux:icon.tabler.star />");

    expect($references->first()->line)->toBe(4);
});

it('skips the icons it was told are built there, and only those', function () {
    // the same tail elsewhere, as a module's own views could have it
    $module = 'modules/shop/resources/views/flux/icon/tabler/home.blade.php';

    expect(scanFiles([
        'resources/views/flux/icon/tabler/home.blade.php' => '<flux:icon.tabler.home />',
        $module => '<flux:icon.tabler.module-home />',
        'resources/views/page.blade.php' => '<flux:icon.tabler.home />',
    ], iconsPath: 'resources/views/flux/icon'))->toBe([
        $module => ['tabler.module-home'],
        'resources/views/page.blade.php' => ['tabler.home'],
    ]);
});

it('reads the literal variant a flux icon tag asks for, and only that', function (string $template, ?string $variant) {
    expect(scanTemplate($template)->first()->variant)->toBe($variant);
})->with([
    'tag' => ['<flux:icon.tabler.home variant="mini" />', 'mini'],
    'name attribute' => ['<flux:icon name="tabler.home" class="size-4" variant=\'duotone\' />', 'duotone'],
    'none' => ['<flux:icon.tabler.home />', null],
    'bound' => ['<flux:icon.tabler.home :variant="$variant" />', null],
    'echoed' => ['<flux:icon.tabler.home variant="{{ $variant }}" />', null],
    'another component' => ['<flux:button icon="tabler.home" variant="primary" />', null],
    'data attribute' => ['<flux:icon.tabler.home data-variant="mini" />', null],
]);

it('reads only a php string that holds nothing but a name, and not a key that looks like one', function () {
    $code = <<<'PHP'
        <?php

        $flag = config('flags.new_checkout');
        $label = __('flags.disabled');
        $colour = \Illuminate\Support\Facades\Config::get('material.primary');
        $page = view('mdi.index');
        $mail = 'support@lucide.dev';
        $style = 'bootstrap.min.css';
        $site = 'www.tabler.io';
        $menu = ['icon' => 'tabler.home', 'label' => __('Home')];
        $doubled = 'tabler.tabler.check';
        ?>
        <span>flags.nl</span>
        PHP;

    expect(scanFiles(['app/Menu.php' => $code], namespaces: ['flags', 'lucide', 'bootstrap', 'material', 'mdi', 'tabler']))
        ->toBe(['app/Menu.php' => ['tabler.home', 'tabler.tabler.check', 'flags.nl']]);
});

it('does not give a reference the variant of a tag that closed before it', function () {
    $references = scanTemplate("<flux:icon.tabler.star variant=\"solid\" />\n@foreach (['tabler.home'] as \$icon)\n    <flux:icon :name=\"\$icon\" />\n@endforeach");

    expect($references->map(fn (IconReference $reference) => [$reference->name(), $reference->variant])->all())
        ->toBe([['tabler.star', 'solid'], ['tabler.home', null]]);
});

it('masks the comment in an @php block whose code starts with a parenthesis on its next line', function () {
    expect(scannedNames("@php\n    (\$a ?? \$b)->x(); // 'tabler.dead'\n@endphp\n<flux:icon.tabler.kept />"))->toBe(['tabler.kept']);
});
