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
 * whose boxes are as wide as each icon. Every Flux variant draws the solid style, Font Awesome's
 * own default: Free draws only some icons in the regular style, and an outline that fell back
 * to solid for the rest would mix the two in Flux's menus, which ask for outline. The regular
 * style is the `regular` variant, which falls back to solid where Free has no regular drawing.
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
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir('solid'))
                ->default()
            )
            // every style is drawn as filled shapes, so there is no stroke to adjust
            ->variant('outline', fn ($variant) => $variant->basedOn('solid')->stroke(false))
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'))
            ->variant('regular', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir('regular'))
                ->fallback(Fallback::defaultVariant())
            );
    }

    /**
     * metadata/icon-families.json gives each icon its search terms and the names it had before,
     * under which svgs-full ships it too: those get its terms and its current name.
     *
     * @return array<array-key, list<string>>
     */
    public function tags(string $packageRoot): array
    {
        $tags = [];

        foreach ($this->readJson("{$packageRoot}/metadata/icon-families.json") as $name => $icon) {
            if (! is_array($icon)) {
                continue;
            }

            $aliases = $this->terms($icon['aliases']['names'] ?? null);
            $tags[$name] = $this->terms([...(array) ($icon['search']['terms'] ?? []), ...$aliases]);

            // svgs-full also ships each icon under its former names, which the metadata only lists as aliases
            foreach ($aliases as $alias) {
                $tags[$alias] ??= $this->terms(array_diff([...$tags[$name], (string) $name], [$alias]));
            }
        }

        return $tags;
    }
}
