<?php

namespace Ympact\FluxIcons\Services;

use Ympact\FluxIcons\Types\Variant;

class VariantDefinitions
{
    public $variants = [];

    public function name(string $name): Variant
    {
        return $this->addVariant(new Variant(
            name: $name
        ));
    }

    /**
     * Add a variant definition
     */
    private function addVariant(Variant $variant): Variant
    {
        // we need to validate that the variant name is unique
        if (in_array($variant->getName(), array_map(fn (Variant $v) => $v->getName(), $this->variants))) {
            throw new \InvalidArgumentException('Variant name '.$variant->getName().' already defined.');
        }
        $this->variants[] = $variant;

        return $variant;
    }
}
