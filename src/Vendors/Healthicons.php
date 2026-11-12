<?php

namespace Ympact\FluxIcons\Vendors;

use Illuminate\Support\Str;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Health icons.
 *
 * @see https://healthicons.org
 *
 * Icons are drawn at 48 pixels in one subdirectory per category. Some also ship a 24 pixel
 * filled drawing, which mini and micro use when it exists.
 */
class Healthicons extends Vendor
{
    public function package(): string
    {
        return 'healthicons';
    }

    public function basePath(): string
    {
        return 'public/icons/svg';
    }

    public function license(): string
    {
        return 'CC0-1.0';
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir('outline/*'))
                ->stroke(false)
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir('filled/*'))
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant
                ->basedOn('solid')
                ->source(Source::dir('filled-24px/*'))
                ->fallback(Fallback::variant('solid'))
            )
            ->variant('micro', fn ($variant) => $variant
                ->basedOn('solid')
                ->source(Source::dir('filled-24px/*'))
                ->fallback(Fallback::variant('solid'))
            );
    }

    /**
     * File names use underscores and the odd capital, and `0` is not a usable component name.
     */
    public function iconName(string $name): string
    {
        if ($name === '0') {
            return 'zero';
        }

        return Str::of($name)->replace('_', '-')->lower()->toString();
    }

    /**
     * meta-data.json gives each icon a title and tags, by its file name as `id`. Tags such as
     * `dhis2:ambulance` are identifiers in DHIS2, the health software the icons were first drawn for,
     * rather than search terms.
     *
     * @return array<array-key, list<string>>
     */
    public function tags(string $packageRoot): array
    {
        return collect($this->readJson("{$packageRoot}/public/icons/meta-data.json"))
            ->filter(fn ($icon) => is_array($icon) && is_string($icon['id'] ?? null))
            ->mapWithKeys(fn (array $icon) => [$icon['id'] => array_values(array_filter(
                $this->terms([$icon['title'] ?? null, ...(array) ($icon['tags'] ?? [])]),
                fn (string $term) => ! str_starts_with($term, 'dhis2:')
            ))])
            ->all();
    }
}
