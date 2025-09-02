<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Collection;

class IconManager
{

    public function currentVendors()
    {
        // get the directory names from resources/flux/icons
        return collect(glob(resource_path('views/flux/icon/*'), GLOB_ONLYDIR))
            ->map(fn($dir) => basename($dir));
    }

    public function currentIcons(?Collection $vendors = null)
    {
        $vendors = $vendors ?? $this->currentVendors();

        // get the SVG file names from resources/flux/icons/{vendor}/{icon}.blade.php and group them by vendor
        return $vendors->mapWithKeys(function ($vendor) {
            return [
                $vendor => collect(glob(resource_path("views/flux/icon/$vendor/*.blade.php")))
                    ->map(fn($file) => basename($file, '.blade.php'))
            ];
        });
    }
}