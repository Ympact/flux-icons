<?php

namespace Ympact\FluxIcons\Variants;

use Illuminate\Support\Collection;
use Ympact\FluxIcons\Sources\Source;

/**
 * A single variant a vendor can produce.
 *
 * Variant names are free-form: on top of the four Flux asks for, a vendor can
 * define its own (duotone, thin, bold, ...) which are emitted into the built
 * component and become available as `<flux:icon.tabler.home variant="duotone" />`.
 *
 * Properties are left null until they are set, so that a variant deriving from
 * another one (see basedOn()) can tell "not configured" apart from "configured
 * to the same value as the default".
 */
class Variant
{
    protected ?Template $template = null;

    protected ?int $size = null;

    protected ?string $classes = null;

    protected ?Source $source = null;

    protected ?Stroke $stroke = null;

    protected bool $strokeDisabled = false;

    /** @var Collection<int, Fill> */
    protected Collection $fills;

    protected ?Fallback $fallback = null;

    /** @var array<string, string|null> */
    protected array $attributes = [];

    protected bool $isDefault = false;

    protected ?string $basedOn = null;

    public function __construct(protected string $name)
    {
        $this->fills = new Collection;
    }

    /**
     * Copies get their own fills collection, so that adding a fill to a resolved
     * variant cannot reach back into the variant it was copied from.
     */
    public function __clone(): void
    {
        $this->fills = clone $this->fills;
    }

    /*
    |--------------------------------------------------------------------------
    | Definition
    |--------------------------------------------------------------------------
    */

    public function template(Template $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function size(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function classes(string $classes): static
    {
        $this->classes = $classes;

        return $this;
    }

    public function source(Source $source): static
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Set the stroke for this variant, or pass false for vendors whose icons
     * carry no adjustable stroke.
     */
    public function stroke(Stroke|bool $stroke): static
    {
        if ($stroke === false) {
            $this->stroke = null;
            $this->strokeDisabled = true;

            return $this;
        }

        if ($stroke === true) {
            $stroke = new Stroke;
        }

        $this->stroke = $stroke;
        $this->strokeDisabled = false;

        return $this;
    }

    public function fill(Fill $fill): static
    {
        $this->fills->push($fill);

        return $this;
    }

    public function fallback(Fallback $fallback): static
    {
        $this->fallback = $fallback;

        return $this;
    }

    /**
     * @param  array<string, string|null>  $attributes
     */
    public function attributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Mark this variant as the vendor's base variant: the one whose sources are
     * enumerated to discover which icons exist, and the target of
     * Fallback::defaultVariant().
     */
    public function default(bool $isDefault = true): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    /**
     * Inherit template, source, stroke, fills and attributes from another variant.
     *
     * Replaces the v1 `base` config key used to derive mini/micro from solid.
     */
    public function basedOn(string $variant): static
    {
        $this->basedOn = $variant;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolution
    |--------------------------------------------------------------------------
    */

    public function getName(): string
    {
        return $this->name;
    }

    public function getTemplate(): Template
    {
        return $this->template ?? Template::Outline;
    }

    public function getSize(): int
    {
        return $this->size ?? $this->getFluxVariant()?->size() ?? 24;
    }

    public function getClasses(): ?string
    {
        return $this->classes ?? $this->getFluxVariant()?->classes();
    }

    public function getSource(): ?Source
    {
        return $this->source;
    }

    public function getStroke(): ?Stroke
    {
        if ($this->strokeDisabled || ! $this->getTemplate()->usesStroke()) {
            return null;
        }

        return $this->stroke;
    }

    /**
     * @return Collection<int, Fill>
     */
    public function getFills(): Collection
    {
        return $this->fills;
    }

    /**
     * Whether this variant paints more than one layer (duotone and friends).
     */
    public function isMultitone(): bool
    {
        return $this->fills->count() > 1;
    }

    public function getFallback(): ?Fallback
    {
        return $this->fallback;
    }

    /**
     * @return array<string, string|null>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function getBasedOn(): ?string
    {
        return $this->basedOn;
    }

    /**
     * Whether this variant fills one of the slots the Flux component requests.
     */
    public function getFluxVariant(): ?FluxVariant
    {
        return FluxVariant::tryFrom($this->name);
    }

    /**
     * Copy everything that was not explicitly set on this variant from its parent.
     */
    public function inheritFrom(self $parent): static
    {
        // size and classes are deliberately not inherited: mini stays 20px even when
        // it derives from a solid variant that was resized
        $this->template ??= $parent->template;
        $this->source ??= $parent->source;
        $this->fallback ??= $parent->fallback;

        // only adopt the parent's stroke, and its disabled flag, when this variant
        // said nothing about its own stroke either way
        if (! $this->strokeDisabled && $this->stroke === null) {
            $this->stroke = $parent->stroke;
            $this->strokeDisabled = $parent->strokeDisabled;
        }

        if ($this->fills->isEmpty()) {
            $this->fills = $parent->fills;
        }

        $this->attributes = array_merge($parent->attributes, $this->attributes);

        return $this;
    }
}
