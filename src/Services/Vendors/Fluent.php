<?php

namespace Ympact\FluxIcons\Services\Vendors;


class Fluent
{
    /**
     * Determine the correct suffix for the solid icon
     * @param int $size size of the resource icon
     * @return string
     */
    public static function sourceSolidSuffix($size): string
    {
        return "_{$size}_filled";
    }

}