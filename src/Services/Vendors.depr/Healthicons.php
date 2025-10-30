<?php

namespace Ympact\FluxIcons\Services\Vendors;

use DOMDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Types\SvgPath;

class Healthicons
{
    /**
     * Determine the final name of the icon
     * @param \Ympact\FluxIcons\Types\Icon $icon
     * @return string
     */
    public static function name(Icon $icon): string
    {
        $name = (string) $icon->getBaseName();
        if($name == '0') {
            return 'zero';
        }

        return Str::of($name)
            ->replace('_', '-')
            ->lower()
            ->toString();
    }
}