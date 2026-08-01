<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IconManager
{
    /**
     * Resolve the name of a built icon directory back to its key in the vendors config.
     *
     * Icons are published to the vendor's namespace, which does not always equal the
     * config key (the `material-icons` vendor for instance publishes to `material`).
     * Returns null for directories that do not belong to a configured vendor, such as
     * the `flux-icons` directory holding the published helper icons.
     */
    public static function resolveVendorKey(string $directory): ?string
    {
        $vendors = collect((array) config('flux-icons.vendors', []));

        if ($vendors->has($directory)) {
            return $directory;
        }

        foreach ($vendors as $key => $vendor) {
            $namespace = is_array($vendor) ? ($vendor['namespace'] ?? $key) : $key;

            if (Str::slug((string) $namespace) === $directory) {
                return (string) $key;
            }
        }

        return null;
    }

    public static function currentVendors()
    {
        // get the directory names from resources/flux/icons
        return collect(glob(resource_path('views/flux/icon/*'), GLOB_ONLYDIR))
            ->map(fn ($dir) => basename($dir));
    }

    public static function installedIcons(?Collection $vendors = null)
    {
        $vendors = $vendors ?? self::currentVendors();

        // get the SVG file names from resources/flux/icons/{vendor}/{icon}.blade.php and group them by vendor
        return $vendors->mapWithKeys(function ($vendor) {
            return [
                $vendor => collect(glob(resource_path("views/flux/icon/$vendor/*.blade.php")))
                    ->map(fn ($file) => basename($file, '.blade.php')),
            ];
        });
    }
}
