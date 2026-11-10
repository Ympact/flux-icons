<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Flag icons.
 *
 * @see https://github.com/lipis/flag-icons
 *
 * Flags keep their own colours, so they are copied verbatim and every variant is built from
 * the same files. The `ratio` option picks the 4x3 flags (the default) or the square ones.
 */
class Flags extends Vendor
{
    public function package(): string
    {
        return 'flag-icons';
    }

    public function basePath(): string
    {
        return 'flags';
    }

    public function name(): string
    {
        return 'Flag Icons';
    }

    public function license(): string
    {
        return 'MIT';
    }

    /**
     * @return array<string, array<int, string|int>>
     */
    public function options(): array
    {
        return [
            'ratio' => ['4x3', '1x1'],
        ];
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                ->template(Template::Raw)
                ->source(Source::dir((string) $this->option('ratio', '4x3')))
                ->stroke(false)
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant->basedOn('outline'))
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }
}
