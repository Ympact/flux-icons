<?php

namespace Ympact\FluxIcons\Tests\Vendor\Beta;

class Beta
{
    public static function sourceSolidSuffix(string $variant, int $size = 24): string
    {
        return "_{$size}_filled";
    }
}
