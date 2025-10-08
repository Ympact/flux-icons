<?php

namespace Ympact\FluxIcons\Contracts;

abstract class Vendor implements VendorInterface
{
    public $style = 'monotone'; // duotone

    public $stroke = 1; // stroke width for outline style

    public function variants(): array
    {
        return [
            'outline', 
            'solid', 
            'mini', 
            'micro'
        ];
    }

    public function sizes(): array
    {
        return [
            'outline' => 'md', 
            'solid' => 'md', 
            'mini' => 'sm', 
            'micro' => 'xs'
        ];
    }

}