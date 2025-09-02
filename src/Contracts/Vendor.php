<?php

namespace Ympact\FluxIcons\Contracts;

abstract class Vendor implements VendorInterface
{


    public function variants(): array
    {
        return ['outline', 'solid', 'mini', 'micro'];
    }
}