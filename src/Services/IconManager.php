<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use SplFileInfo;

/**
 * Lists the icons built into the application, by the directory each vendor publishes to.
 */
class IconManager
{
    public static function currentVendors()
    {
        // listed without glob(), which reads brackets in the project path as a pattern
        $directory = resource_path('views/flux/icon');

        return collect(File::isDirectory($directory) ? File::directories($directory) : [])
            ->map(fn ($dir) => basename($dir));
    }

    public static function installedIcons(?Collection $vendors = null)
    {
        $vendors = $vendors ?? self::currentVendors();

        // get the SVG file names from resources/flux/icons/{vendor}/{icon}.blade.php and group them by vendor
        return $vendors->mapWithKeys(function ($vendor) {
            $directory = resource_path("views/flux/icon/$vendor");

            return [
                $vendor => collect(File::isDirectory($directory) ? File::files($directory) : [])
                    ->map(fn (SplFileInfo $file) => $file->getFilename())
                    ->filter(fn (string $name) => str_ends_with($name, '.blade.php'))
                    ->map(fn (string $name) => basename($name, '.blade.php'))
                    ->values(),
            ];
        });
    }
}
