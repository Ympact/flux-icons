<?php

namespace Ympact\FluxIcons\Variants;

/**
 * What to do when a vendor ships no source file for a variant.
 *
 * Replaces the string|false|callable union used in the v1 config, so the intent
 * is explicit at the definition site:
 *
 *     Fallback::variant('outline')  build this variant from the outline source
 *     Fallback::defaultVariant()    build it from whatever the default variant is
 *     Fallback::skip()              do not build the icon at all
 */
class Fallback
{
    private function __construct(
        private readonly ?string $variant,
        private readonly bool $skip,
        private readonly bool $useDefault,
    ) {}

    public static function variant(string $name): self
    {
        return new self($name, false, false);
    }

    public static function defaultVariant(): self
    {
        return new self(null, false, true);
    }

    public static function skip(): self
    {
        return new self(null, true, false);
    }

    public function skips(): bool
    {
        return $this->skip;
    }

    /**
     * Resolve the variant name to fall back to, given the set's default variant.
     */
    public function resolve(?string $defaultVariant = null): ?string
    {
        if ($this->skip) {
            return null;
        }

        return $this->useDefault ? $defaultVariant : $this->variant;
    }
}
