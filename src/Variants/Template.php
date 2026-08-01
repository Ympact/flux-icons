<?php

namespace Ympact\FluxIcons\Variants;

/**
 * How the SVG root element of a variant is rendered.
 *
 * This is deliberately separate from the variant name: a vendor may ship its
 * "outline" icons as closed filled shapes (Bootstrap, MDI, Material) in which
 * case the outline variant is rendered with the solid template.
 */
enum Template: string
{
    /** Stroked paths that follow the icon's stroke width. */
    case Outline = 'outline';

    /** Filled paths using the current text colour. */
    case Solid = 'solid';

    /** The source SVG is copied verbatim, no transformation is applied. */
    case Raw = 'raw';

    /**
     * Attributes applied to the <svg> element for this template.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return match ($this) {
            self::Outline => [
                'fill' => 'none',
                'stroke' => 'currentColor',
            ],
            self::Solid => [
                'fill' => 'currentColor',
            ],
            self::Raw => [],
        };
    }

    /**
     * Whether icons rendered with this template are affected by a stroke width.
     */
    public function usesStroke(): bool
    {
        return $this === self::Outline;
    }
}
