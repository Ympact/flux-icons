<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Font Awesome Free: the solid and regular styles. The brand icons are FontAwesomeBrands.
 *
 * @see https://fontawesome.com/icons
 *
 * Builds from svgs-full, where every icon sits in the same square box, rather than from svgs,
 * whose boxes are as wide as each icon. Solid is the default variant, as it is Font Awesome's:
 * Free draws only some icons in the regular style, so the outline variant falls back to solid.
 */
class FontAwesome extends Vendor
{
    public function package(): string
    {
        return '@fortawesome/fontawesome-free';
    }

    public function basePath(): string
    {
        return 'svgs-full';
    }

    public function license(): string
    {
        return 'CC-BY-4.0';
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                // regular icons are filled shapes, so there is no stroke to adjust
                ->template(Template::Solid)
                ->source(Source::dir('regular'))
                ->stroke(false)
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir('solid'))
                ->default()
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }

    /**
     * metadata/icon-families.json gives each icon its search terms and the names it had before.
     *
     * @return array<string, list<string>>
     */
    public function tags(string $packageRoot): array
    {
        return collect($this->readJson("{$packageRoot}/metadata/icon-families.json"))
            ->filter(fn ($icon) => is_array($icon))
            ->map(fn (array $icon) => $this->terms(array_merge(
                (array) ($icon['search']['terms'] ?? []),
                (array) ($icon['aliases']['names'] ?? []),
            )))
            ->all();
    }
}
