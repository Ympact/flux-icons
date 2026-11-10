<?php

namespace Ympact\FluxIcons\Variants;

/**
 * A fill layer within a variant.
 *
 * Monotone variants need no fill definition at all; the template supplies
 * `fill="currentColor"`. Multitone variants (duotone and friends) ship several
 * layers in one SVG that have to be told apart and re-coloured. A Fill both
 * selects the source paths belonging to a layer and describes how that layer
 * should be rendered.
 *
 * Phosphor's duotone icons for instance draw the background layer with
 * `opacity="0.2"` and the foreground layer without any opacity:
 *
 *     new Fill('background', matchOpacity: 0.2, opacity: 0.2)
 *     new Fill('foreground')
 */
class Fill
{
    /**
     * @param  string  $name  identifies the layer, e.g. background/foreground
     * @param  float|null  $matchOpacity  select source paths carrying this opacity
     * @param  string|null  $matchColor  select source paths carrying this fill colour
     * @param  string  $color  the colour to render the layer in
     * @param  float|null  $opacity  the opacity to render the layer at
     */
    public function __construct(
        public readonly string $name = 'primary',
        public readonly ?float $matchOpacity = null,
        public readonly ?string $matchColor = null,
        public readonly string $color = 'currentColor',
        public readonly ?float $opacity = null,
    ) {}

    /**
     * Whether this fill selects a specific subset of the source paths.
     *
     * A fill without any matcher is the catch-all layer: it takes every path
     * that no other fill claimed.
     */
    public function isCatchAll(): bool
    {
        return $this->matchOpacity === null && $this->matchColor === null;
    }

    /**
     * Whether the given source path attributes belong to this fill layer.
     *
     * @param  array<string, string>  $attributes
     */
    public function matches(array $attributes): bool
    {
        if ($this->isCatchAll()) {
            return true;
        }

        if ($this->matchOpacity !== null) {
            $opacity = $attributes['opacity'] ?? $attributes['fill-opacity'] ?? null;

            if ($opacity === null || abs((float) $opacity - $this->matchOpacity) > 0.0001) {
                return false;
            }
        }

        if ($this->matchColor !== null) {
            $color = $attributes['fill'] ?? null;

            if ($color === null || strcasecmp(trim($color), trim($this->matchColor)) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Attributes applied to the paths of this layer.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_filter([
            'fill' => $this->color,
            'opacity' => $this->opacity !== null ? (string) $this->opacity : null,
        ], fn ($value) => $value !== null);
    }
}
