<?php

namespace Ympact\FluxIcons\Variants;

/**
 * Stroke settings for variants rendered with the outline template.
 */
class Stroke
{
    public function __construct(
        public readonly float $width = 1.5,
        public readonly ?string $linecap = 'round',
        public readonly ?string $linejoin = 'round',
    ) {}

    public function withWidth(float $width): self
    {
        return new self($width, $this->linecap, $this->linejoin);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_filter([
            'stroke-width' => (string) round($this->width, 2),
            'stroke-linecap' => $this->linecap,
            'stroke-linejoin' => $this->linejoin,
        ], fn ($value) => $value !== null);
    }
}
