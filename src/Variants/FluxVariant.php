<?php

namespace Ympact\FluxIcons\Variants;

/**
 * The variants the Flux icon component itself asks for.
 *
 * Flux components (badges, buttons, ...) request these by name, so every built
 * icon has to be able to answer all four. A vendor is free to define additional
 * variants on top of these - see Ympact\FluxIcons\Variants\VariantSet.
 */
enum FluxVariant: string
{
    case Outline = 'outline';
    case Solid = 'solid';
    case Mini = 'mini';
    case Micro = 'micro';

    /**
     * The size Flux renders this variant at, in pixels.
     */
    public function size(): int
    {
        return match ($this) {
            self::Outline, self::Solid => 24,
            self::Mini => 20,
            self::Micro => 16,
        };
    }

    /**
     * The Tailwind classes Flux applies to this variant.
     */
    public function classes(): string
    {
        return match ($this) {
            self::Outline, self::Solid => '[:where(&)]:size-6',
            self::Mini => '[:where(&)]:size-5',
            self::Micro => '[:where(&)]:size-4',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(fn (self $variant) => $variant->value, self::cases());
    }
}
