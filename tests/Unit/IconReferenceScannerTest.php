<?php

use Illuminate\Support\Collection;
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
