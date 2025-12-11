<?php

namespace Ympact\FluxIcons\Services\Vendors;

use Illuminate\Support\Str;
use Ympact\FluxIcons\Types\Icon;

class Healthicons
{
    /**
     * Determine the final name of the icon
     */
    public static function name(Icon $icon): string
    {
        $name = (string) $icon->getBaseName();
        if ($name == '0') {
            return 'zero';
        }

        return Str::of($name)
            ->replace('_', '-')
            ->lower()
            ->toString();
    }
}
