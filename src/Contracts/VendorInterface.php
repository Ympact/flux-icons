<?php

namespace Ympact\FluxIcons\Contracts;

interface VendorInterface
{
    public function name(): string;

    public function package(): string;

    public function variants(): array;

    public function sizes(): array;
}