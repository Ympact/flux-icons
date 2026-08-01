<?php

namespace Ympact\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Fill;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Phosphor icons.
 *
 * @see https://phosphoricons.com
 *
 * Demonstrates the two things the v1 config could not express:
 *
 * - a variant Flux does not know about (`duotone`), emitted alongside the four
 *   Flux variants and usable as <flux:icon.phosphor.heart variant="duotone" />
 * - a build option (`weight`), which picks which of the six weights the outline
 *   variant is built from
 *
 * Files are laid out as assets/{weight}/{icon}-{weight}.svg, except for the
 * regular weight which carries no suffix.
 */
class Phosphor extends Vendor
{
    public function package(): string
    {
        return '@phosphor-icons/core';
    }

    public function basePath(): string
    {
        return 'assets';
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
            'weight' => ['thin', 'light', 'regular', 'bold'],
        ];
    }

    public function variants(): VariantSet
    {
        $weight = (string) $this->option('weight', 'regular');

        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                // Phosphor draws its outlines as closed filled shapes rather than
                // strokes, so the stroke width is not adjustable
                ->template(Template::Solid)
                ->source($this->weightSource($weight))
                ->stroke(false)
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source($this->weightSource('fill'))
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'))
            ->variant('duotone', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source($this->weightSource('duotone'))
                // the background layer is the one drawn at 20% opacity, everything
                // else is foreground
                ->fill(new Fill('background', matchOpacity: 0.2, opacity: 0.2))
                ->fill(new Fill('foreground'))
                ->fallback(Fallback::defaultVariant())
            );
    }

    /**
     * Every weight lives in its own directory and repeats the weight as a file
     * name suffix, except for regular.
     */
    protected function weightSource(string $weight): Source
    {
        $source = Source::dir($weight);

        return $weight === 'regular' ? $source : $source->suffix("-{$weight}");
    }
}
