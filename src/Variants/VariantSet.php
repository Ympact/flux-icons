<?php

namespace Ympact\FluxIcons\Variants;

use Closure;
use Countable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * The set of variants a vendor produces.
 *
 * Defined fluently from a vendor class:
 *
 *     VariantSet::make()
 *         ->variant('outline', fn (Variant $v) => $v->source(Source::dir('icons/outline'))->default())
 *         ->variant('solid', fn (Variant $v) => $v->template(Template::Solid)->source(Source::dir('icons/filled')))
 *         ->variant('mini', fn (Variant $v) => $v->basedOn('solid'))
 *         ->variant('duotone', fn (Variant $v) => $v->template(Template::Solid)->fill(...));
 *
 * @implements IteratorAggregate<string, Variant>
 */
class VariantSet implements Countable, IteratorAggregate
{
    /** @var array<string, Variant> */
    protected array $variants = [];

    final public function __construct() {}

    public static function make(): static
    {
        return new static;
    }

    /**
     * Define a variant. Redefining an existing name reconfigures it, which lets a
     * vendor subclass adjust a single variant without restating the whole set.
     *
     * @param  Closure(Variant): mixed|null  $configure
     */
    public function variant(string $name, ?Closure $configure = null): static
    {
        $variant = $this->variants[$name] ??= new Variant($name);

        if ($configure) {
            $configure($variant);
        }

        return $this;
    }

    /**
     * Remove a variant, for vendors that cannot supply it at all.
     */
    public function without(string $name): static
    {
        unset($this->variants[$name]);

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->variants[$name]);
    }

    public function get(string $name): ?Variant
    {
        return $this->variants[$name] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->variants);
    }

    /**
     * @return Collection<string, Variant>
     */
    public function all(): Collection
    {
        return new Collection($this->variants);
    }

    /**
     * The variant whose sources are enumerated to discover which icons exist.
     *
     * Falls back to the first defined variant when no variant was marked default.
     */
    public function default(): Variant
    {
        if ($this->variants === []) {
            throw new InvalidArgumentException('A vendor must define at least one variant.');
        }

        foreach ($this->variants as $variant) {
            if ($variant->isDefault()) {
                return $variant;
            }
        }

        return reset($this->variants);
    }

    public function defaultName(): string
    {
        return $this->default()->getName();
    }

    /**
     * The variants Flux itself requests, in Flux's own order.
     *
     * @return Collection<string, Variant>
     */
    public function fluxVariants(): Collection
    {
        return $this->all()
            ->filter(fn (Variant $variant) => $variant->getFluxVariant() !== null)
            ->sortBy(fn (Variant $variant) => array_search($variant->getName(), FluxVariant::names(), true));
    }

    /**
     * Variants beyond the four Flux knows about, such as duotone or a weight.
     *
     * @return Collection<string, Variant>
     */
    public function vendorVariants(): Collection
    {
        return $this->all()->filter(fn (Variant $variant) => $variant->getFluxVariant() === null);
    }

    /**
     * Flux variants this vendor does not define. A built icon that misses any of
     * these cannot answer every request the Flux components make.
     *
     * @return array<int, string>
     */
    public function missingFluxVariants(): array
    {
        return array_values(array_diff(FluxVariant::names(), $this->names()));
    }

    /**
     * Apply basedOn() inheritance and return the resolved set.
     *
     * Inheritance is resolved in definition order, so a variant may derive from
     * another variant that itself derives from a third.
     */
    public function resolve(): static
    {
        $resolved = new static;

        foreach ($this->variants as $name => $variant) {
            $resolved->variants[$name] = $variant;

            if (! $parentName = $variant->getBasedOn()) {
                continue;
            }

            $parent = $resolved->variants[$parentName] ?? $this->variants[$parentName] ?? null;

            if (! $parent) {
                throw new InvalidArgumentException(
                    "Variant [{$name}] is based on [{$parentName}], which is not defined."
                );
            }

            $variant->inheritFrom($parent);
        }

        return $resolved;
    }

    /**
     * Resolve which variant actually supplies the source for the given variant
     * when the vendor ships no file for it.
     */
    public function fallbackFor(string $name): ?Variant
    {
        $variant = $this->get($name);

        if (! $variant || ! $fallback = $variant->getFallback()) {
            return null;
        }

        $target = $fallback->resolve($this->defaultName());

        return $target !== null && $target !== $name ? $this->get($target) : null;
    }

    public function count(): int
    {
        return count($this->variants);
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->variants);
    }
}
