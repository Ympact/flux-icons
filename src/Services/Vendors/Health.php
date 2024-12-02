<?php

namespace Ympact\FluxIcons\Services\Vendors;

use DOMDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Types\SvgPath;

class Health
{
    /**
     * Determine the final name of the icon
     * @param \Ympact\FluxIcons\Types\Icon $icon
     * @return string
     */
    public static function name(Icon $icon): string
    {
        return Str::replace('_', '-' ,$icon->getBaseName());
    }
}