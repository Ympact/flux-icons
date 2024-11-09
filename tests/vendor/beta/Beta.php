<?php

namespace Ympact\FluxIcons\Tests\Vendor\Beta;


class Beta
{
    /**
     * Determine the correct suffix for the solid icon
     * @param string $variant (solid, outline, mini, micro)
     * @param int $size size of the resource icon (24, 20, 16)
     * @return string
     */
    public static function sourceSolidSuffix(string $variant, int $size): string
    {
        return "_{$size}_filled";
    }

}