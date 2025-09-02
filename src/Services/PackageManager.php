<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class PackageManager
{

    public static function fluxVersion()
    {
        $composerFile = base_path('composer.lock');

        if(!File::exists($composerFile)){
            throw new \RuntimeException("composer.lock not found. Can't determine Livewire\Flux version.");
        }
        $composerLock = json_decode(file_get_contents($composerFile), true);
        $packages = collect($composerLock['packages']);
        $fluxPackage = $packages->firstWhere('name', 'livewire/flux');

        return $fluxPackage['version'] ?? null;
    }

    // npm update vendor package
    public static function updateVendorPackage(string $vendor, $verbose = false): bool
    {
        $baseConfig = "flux-icons.vendors.{$vendor}";

        if(!config()->has("{$baseConfig}")){
            throw new \RuntimeException("Vendor {$vendor} not found in config.");
        }

        $package = config("{$baseConfig}.package");
        $arg = $verbose ? '' : '-s';
        exec("npm update {$package} {$arg}", $output, $result);

        if($result !== 0){
            throw new \RuntimeException("Failed to update package: {$package}. ".implode("\n", $output));
        }
        return true;
    }
}