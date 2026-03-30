<?php

namespace Ympact\FluxIcons\Tests\Vendor\Epsilon;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Epsilon
{
    public static function outlineFilter($file, &$icons): bool
    {
        if ($icons === null) {
            $icons = [];
        }

        $filename = pathinfo($file, PATHINFO_FILENAME);

        if (Str::contains($filename, '-outline')) {
            return true;
        }

        if (File::exists(Str::of($file)->before('.svg').'-outline.svg')) {
            if (in_array($filename, $icons)) {
                $key = array_search($filename, $icons);
                unset($icons[$key]);
                $icons[] = $filename.'-outline';
            }

            return false;
        }

        return true;
    }

    public static function solidFilter($file): bool
    {
        $filename = pathinfo($file, PATHINFO_FILENAME);

        if (Str::contains($filename, '-outline')) {
            return false;
        }

        return File::exists(Str::of($file)->before('.svg').'-outline.svg');
    }
}
