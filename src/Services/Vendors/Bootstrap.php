<?php

namespace Ympact\FluxIcons\Services\Vendors;

use Illuminate\Support\Str;

class Bootstrap
{
    
    /**
     * 
     * @param $size size of the resource icon
     * @return boolean
     */
    public static function outlineFilter($file, &$icons): bool
    {
        $filename = pathinfo($file, PATHINFO_FILENAME);
        if (Str::contains($filename,'-fill')) {
            return false;
        }
        return true;
    }

    /**
     * 
     * @param string $file
     * @param array|null $icons
     * @return boolean
     */
    public static function solidFilter($file, &$icons): bool
    {
        // if the icon name ends with -fill it is an solid icon
        $filename = pathinfo($file, PATHINFO_FILENAME);
        if (Str::contains($filename, '-fill')) {
            return true;
        }

        return false;
    }

}