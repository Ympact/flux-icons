<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
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

    /**
     * @return array<string, string|int>
     */
    public function optionDefaults(): array
    {
        return ['ratio' => '4x3'];
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                ->template(Template::Raw)
                ->source(Source::dir((string) $this->option('ratio')))
                ->stroke(false)
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->basedOn('outline')
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }

    /**
     * country.json names the country each flag code stands for.
     *
     * @return array<string, list<string>>
     */
    public function tags(string $packageRoot): array
    {
        return collect($this->readJson("{$packageRoot}/country.json"))
            ->filter(fn ($flag) => is_array($flag) && is_string($flag['code'] ?? null))
            ->mapWithKeys(fn (array $flag) => [$flag['code'] => $this->terms([$flag['name'] ?? null])])
            ->all();
    }
}
