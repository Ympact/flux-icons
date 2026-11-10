<?php

namespace Ympact\FluxIcons\Build;

use Illuminate\Support\Arr;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\Variant;

/**
 * Turns one source file into the <svg> markup of one variant of a built component.
 */
class IconRenderer
{
    /** Attributes every built icon carries, as Flux's own icons do. */
    protected const FLUX_ATTRIBUTES = [
        'data-flux-icon' => '1',
        'xmlns' => 'http://www.w3.org/2000/svg',
        'aria-hidden' => 'true',
        'data-slot' => 'icon',
    ];

    /** How the vendor draws the icon, kept from its source root unless the vendor class says otherwise. */
    protected const VENDOR_PRESENTATION = ['stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule'];

    /**
     * @param  Variant  $variant  The variant whose template, stroke and attributes apply: the one
     *                            being built, or the one it falls back to.
     * @param  string  $building  The Flux variant being built, which the vendor's hooks are told.
     */
    public function render(Vendor $vendor, Variant $variant, string $building, string $file, string $name): string
    {
        $template = $variant->getTemplate();

        $icon = Icon::fromFile($file, $building, $template->value, $name)->process();
        $icon->setPaths($vendor->transform($icon->getPaths(), $icon));

        $stroke = $variant->getStroke();

        if ($stroke !== null) {
            $stroke = $stroke->withWidth($vendor->strokeWidth($icon, $stroke->width));
            $icon->setStrokeWidth($stroke->width, force: true);
        }

        return $icon
            ->setAttributes($this->attributes($vendor, $variant, $icon, $stroke))
            ->setRaw($template === Template::Raw)
            ->toHtml();
    }

    /**
     * The vendor class wins over the source root, and a null value removes an attribute.
     *
     * @return array<string, string>
     */
    protected function attributes(Vendor $vendor, Variant $variant, Icon $icon, ?Stroke $stroke): array
    {
        $attributes = array_merge(self::FLUX_ATTRIBUTES, $variant->getTemplate()->attributes(), $stroke?->attributes() ?? []);

        foreach (Arr::only($icon->getSourceAttributes(), self::VENDOR_PRESENTATION) as $name => $value) {
            $attributes[$name] ??= $value;
        }

        $attributes = array_merge($attributes, $variant->getAttributes(), $vendor->attributes($icon));

        return array_filter($attributes, fn (?string $value) => $value !== null);
    }
}
