<?php

namespace Ympact\FluxIcons\Contracts;

use Ympact\FluxIcons\Variants;

interface VendorInterface
{
    public function name(): string;

    public function package(): string;

    public function variants(): Variants;

    public function sizes(): array;
}