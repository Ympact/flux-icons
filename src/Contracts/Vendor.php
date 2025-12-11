<?php

namespace Ympact\FluxIcons\Contracts;

use Ympact\FluxIcons\Services\VariantDefinitions;
use Ympact\FluxIcons\Types\Fill;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Types\Stroke;
use Ympact\FluxIcons\Variants;

abstract class Vendor implements VendorInterface
{
    public $package = null;

    public $name = null; // optionally a human readable name, otherwise the package name will be used

    // base path within the package where the icons are located /vendor/{$package}
    public $basePath = null;

    // style can be set per variant
    // public $style = 'monotone'; // duotone, multitone, default montone

    public $stroke = 1; // default stroke width in case icons support it. In case not supported, set to null/false

    public $inline = true; // whether the icons can be used as inline SVGs, default true, set to false if not supported

    public $inlineSize = '1em'; // size for inline icons in ems, default 1em set to false if not supported

    public $inlineOffset = '-0.125em'; // offset for inline icons to align with text baseline in ems, default -0.125em set to false if not supported

    /**
     * return the source path/dir for the icons
     *
     * @param  mixed  $variant
     * @param  mixed  $size
     * @return void
     */
    public function source($variant = null, $size = null): string
    {
        return '';
    }

    // or a simple discovery path
    public $source = '';

    /**
     * Variant to support
     *
     * @return array
     */
    public function variants(): Variants
    {
        return Variants::create(function (VariantDefinitions $variant) {
            $variant->name('outline')
                // ->source() // override source directory for the specific variant
                ->stroke(new Stroke(1))
                ->addFill(new Fill('primary', '#fff'))
                ->addFill(new Fill('secondary', '#000'))
                // ->size('md')
                // ->transform(fn() => $this)
                // ->strategy() // the strategy to use in case there is no specific icon for this variant (omit, fallback from different variant, generate, etc)
                ->default(); // mark as default variant, if not specified, first variant will be default
            // ->defaultInline() // default variant for inline usage
        });
    }

    public function sizes(): array
    {
        return [
            'md' => 'size-5',
            'sm' => 'size-4',
            'xs' => 'size-3',
        ];
    }

    public $defaultSize = 'md'; // default size key

    // in case of duotone or multitone icons, define what colors are present in the package such that they can be mapped
    public function colors(): array
    {
        return [];
    }

    /**
     * Default icons to be included from this vendor
     *
     * @return array<string>
     */
    public function defaultIcons(): array
    {
        return [];
    }

    // a method to generate missing icons based on existing ones
    public function generate() {}

    public function transform(Icon $icon): ?Icon
    {
        return $icon;
    }
}
