<?php

namespace Ympact\FluxIcons;

use Ympact\FluxIcons\Services\VariantDefinitions;

class Variants extends VariantDefinitions
{
    /**
     * Create a new variant.
     *
     * @param  callable  $callback  the variant blueprint
     */
    public static function create(callable $callback): VariantDefinitions
    {
        $variants = new VariantDefinitions;

        $callback($variants);

        return $variants;
    }
}