<?php

namespace Ympact\FluxIcons\Contracts;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Types\SvgPath;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * The definition of an icon vendor.
 *
 * A vendor class is the single source of truth for everything the builder needs
 * to know about an icon package: where it comes from, which variants it can
 * produce, and how its icons need to be adjusted. It replaces the v1 combination
 * of a config array plus loose `[Class::class, 'method']` callbacks.
 *
 * Users add their own vendor by extending this class (or an existing vendor) and
 * registering it in config/flux-icons.php.
 */
abstract class Vendor
{
    /**
     * The npm package the icons are installed from.
     */
    abstract public function package(): string;

    /**
     * The variants this vendor can produce.
     */
    abstract public function variants(): VariantSet;

    /*
    |--------------------------------------------------------------------------
    | Identity
    |--------------------------------------------------------------------------
    */

    /**
     * The key the vendor is addressed by on the command line.
     */
    public function key(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    /**
     * Human readable name, used in prompts and in the credits of a built icon.
     */
    public function name(): string
    {
        return Str::headline(class_basename(static::class));
    }

    /**
     * The Flux namespace the icons are published under, which is also the
     * directory they are written to: <flux:icon.{namespace}.{icon} />
     */
    public function namespace(): string
    {
        return $this->key();
    }

    /**
     * The directory inside the npm package that holds the icon directories.
     * Variant sources are resolved relative to it.
     */
    public function basePath(): string
    {
        return '';
    }

    /**
     * The licence the icons are published under, recorded in the credits of a
     * built icon.
     */
    public function license(): ?string
    {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Build behaviour
    |--------------------------------------------------------------------------
    */

    /**
     * Icons built when no icons are passed to the build command.
     *
     * @return array<int, string>
     */
    public function defaultIcons(): array
    {
        return [];
    }

    /**
     * Adjust the name a built icon is published under.
     */
    public function iconName(string $name): string
    {
        return $name;
    }

    /**
     * Adjust the paths of an icon before it is written.
     *
     * @param  Collection<int, SvgPath>  $paths
     * @return Collection<int, SvgPath>
     */
    public function transform(Collection $paths, Icon $icon): Collection
    {
        return $paths;
    }

    /**
     * Adjust the attributes of the <svg> element of an icon.
     *
     * @return array<string, string|null>
     */
    public function attributes(Icon $icon): array
    {
        return [];
    }

    /**
     * Adjust the stroke width for a single icon, for icons whose shapes need a
     * heavier or lighter stroke than the variant default.
     */
    public function strokeWidth(Icon $icon, float $default): float
    {
        return $default;
    }

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */

    /**
     * Build options this vendor exposes, such as a weight or an aspect ratio.
     *
     * Returned as an option name mapped to its allowed values, so the build
     * command can offer them and the config can set a default.
     *
     * @return array<string, array<int, string|int>>
     */
    public function options(): array
    {
        return [];
    }

    /**
     * The option values in effect for this build.
     *
     * @var array<string, string|int>
     */
    protected array $selectedOptions = [];

    /**
     * @param  array<string, string|int>  $options
     */
    public function withOptions(array $options): static
    {
        foreach ($options as $name => $value) {
            $allowed = $this->options()[$name] ?? null;

            if ($allowed === null) {
                throw new \InvalidArgumentException(
                    "Vendor [{$this->key()}] has no option [{$name}]."
                );
            }

            if (! in_array($value, $allowed, false)) {
                throw new \InvalidArgumentException(
                    "[{$value}] is not a valid value for option [{$name}] of vendor [{$this->key()}]. ".
                    'Allowed: '.implode(', ', array_map('strval', $allowed)).'.'
                );
            }

            $this->selectedOptions[$name] = $value;
        }

        return $this;
    }

    /**
     * @return string|int|null
     */
    public function option(string $name, string|int|null $default = null)
    {
        return $this->selectedOptions[$name] ?? $default;
    }

    /**
     * The variants of this vendor with basedOn() inheritance applied.
     */
    public function resolvedVariants(): VariantSet
    {
        return $this->variants()->resolve();
    }
}
