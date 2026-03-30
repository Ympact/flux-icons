<?php

use Tests\TestCase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Ympact\FluxIcons\Services\IconBuilder;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Types\SvgPath;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toMatchSortedValues', function (array $expected) {
    $actual = $this->value;

    if ($actual instanceof Collection) {
        $actual = $actual->values()->all();
    }

    sort($actual);
    sort($expected);

    Assert::assertSame($expected, $actual);

    return $this;
});

expect()->extend('toBeSvgMarkup', function (array $fragments = []) {
    Assert::assertStringContainsString('<svg', $this->value);

    foreach ($fragments as $fragment) {
        Assert::assertStringContainsString($fragment, $this->value);
    }

    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function fixtureConfig(): array
{
    return require __DIR__.'/config/flux-icons.php';
}

function fixturePath(string $path = ''): string
{
    return base_path('tests'.($path !== '' ? '/'.$path : ''));
}

function alphaOutlineFile(string $name = 'arrow-left'): string
{
    return fixturePath("vendor/alpha/icons/outline/{$name}.svg");
}

function alphaIconConfig(array $overrides = []): array
{
    $base = [
        'variants' => [
            'outline' => [
                'template' => 'outline',
                'source' => 'tests/vendor/alpha/icons/outline',
                'path_attributes' => ['stroke-linecap' => 'round'],
            ],
            'solid' => [
                'template' => 'solid',
                'source' => 'tests/vendor/alpha/icons/filled',
                'path_attributes' => ['fill-rule' => 'evenodd'],
            ],
            'mini' => ['template' => 'solid', 'base' => 'solid', 'size' => 20],
            'micro' => ['template' => 'solid', 'base' => 'solid', 'size' => 16],
        ],
    ];

    return array_replace_recursive($base, $overrides);
}

function makeBaseBuilderIcon(IconBuilder $builder, ?string $file = null): Icon
{
    return $builder->buildIcon('outline', $file ?? alphaOutlineFile());
}

function makeSvgPath(string $d): SvgPath
{
    $dom = new \DOMDocument;
    $dom->loadXML('<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    $node = $dom->createElement('path');
    $node->setAttribute('d', $d);
    $dom->documentElement->appendChild($node);

    return new SvgPath($node);
}

function makeVendorTestIcon(string $variant = 'outline', string $basename = 'arrow-left'): Icon
{
    $icon = new Icon(alphaIconConfig(), $variant, alphaOutlineFile($basename));
    $icon->process();

    return $icon;
}

function removeDirectory(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    foreach (glob($dir.'/*') as $file) {
        is_dir($file) ? removeDirectory($file) : unlink($file);
    }

    rmdir($dir);
}

function writeComposerLock(string $dir, array $data): void
{
    file_put_contents($dir.'/composer.lock', json_encode($data, JSON_PRETTY_PRINT));
}
