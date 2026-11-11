<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Build\IconRenderer;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;
use Ympact\FluxIcons\Vendors\Flags;
use Ympact\FluxIcons\Vendors\Lucide;
use Ympact\FluxIcons\Vendors\Tabler;

/**
 * Render a source drawing as the outline variant of a vendor.
 *
 * @return array{0: string, 1: array<string, string>} the markup, and the attributes of its root
 */
function renderOutline(Vendor $vendor, string $source): array
{
    $file = sys_get_temp_dir().'/flux-icons-render-'.Str::random(8).'.svg';
    File::put($file, $source);

    try {
        $html = (new IconRenderer)->render($vendor, $vendor->resolvedVariants()->get('outline'), 'outline', $file, 'icon');
    } finally {
        File::delete($file);
    }

    $dom = new DOMDocument;
    $dom->loadHTML($html, LIBXML_NOERROR);
    $root = $dom->getElementsByTagName('svg')->item(0);
    $attributes = [];

    foreach ($root->attributes as $attribute) {
        $attributes[$attribute->nodeName] = $attribute->nodeValue;
    }

    return [$html, $attributes];
}

it('draws lucide with the round caps and joins of its own root', function () {
    [, $root] = renderOutline(new Lucide, '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18"/></svg>');

    expect($root)->toMatchArray([
        'fill' => 'none',
        'stroke' => 'currentColor',
        'stroke-width' => '1.5',
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
    ]);
});

it('draws the caps and joins of the stroke the vendor class sets over those of the source root', function () {
    [, $root] = renderOutline(new Tabler, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-linecap="butt" stroke-linejoin="miter"><path d="M3 12h18"/></svg>');

    expect($root)->toMatchArray(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']);
});

it('lets the vendor class override or remove what the source root sets', function () {
    $vendor = new class extends Vendor
    {
        public function package(): string
        {
            return 'test';
        }

        public function variants(): VariantSet
        {
            return VariantSet::make()->variant('outline', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir())
                ->attributes(['fill-rule' => 'nonzero', 'clip-rule' => null])
                ->default()
            );
        }
    };

    [, $root] = renderOutline($vendor, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill-rule="evenodd" clip-rule="evenodd"><path d="M3 3h18v18H3z"/></svg>');

    expect($root)->toMatchArray(['fill' => 'currentColor', 'fill-rule' => 'nonzero'])
        ->and($root)->not->toHaveKey('clip-rule');
});

it('nests a flag as flag-icons draws it, without filling it in the text colour', function () {
    [$html, $root] = renderOutline(new Flags, '<svg xmlns="http://www.w3.org/2000/svg" id="flag-icons-xx" viewBox="0 0 640 480"><path fill="#fff" d="M0 0h640v480H0z"/></svg>');

    expect($root)->not->toHaveKey('fill')
        ->and($html)->toContain('<svg id="flag-icons-xx"')
        ->and($html)->toContain('fill="#fff"');
});
