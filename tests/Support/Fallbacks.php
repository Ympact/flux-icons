<?php

namespace Tests\Support;

use Ympact\FluxIcons\Types\Icon;

class Fallbacks
{
    public static function toOutline(Icon $baseIcon, string $variant, string $baseVariant): string
    {
        return 'outline';
    }
}
