<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Facades\File;

class PackageManager
{
    public static function fluxVersion()
    {
        $composerFile = base_path('composer.lock');

        if (! File::exists($composerFile)) {
            throw new \RuntimeException("composer.lock not found. Can't determine Livewire\Flux version.");
        }
        $composerLock = json_decode(file_get_contents($composerFile), true);
        $packages = collect($composerLock['packages']);
        $fluxPackage = $packages->firstWhere('name', 'livewire/flux');

        return $fluxPackage['version'] ?? null;
    }

    /**
     * Determine whether the installed Flux version is at least the given version.
     *
     * Versions from composer.lock may be prefixed with a "v" and are compared with
     * version_compare() so that e.g. 2.10.0 correctly sorts above 2.2.6.
     * Non-release versions (dev-main, branch aliases) are treated as up to date.
     */
    public static function fluxVersionAtLeast(string $version): bool
    {
        $fluxVersion = static::fluxVersion();

        if (! $fluxVersion) {
            return false;
        }

        $fluxVersion = ltrim($fluxVersion, 'vV');

        // dev-main, dev-master, feature branches, ... cannot be compared reliably,
        // assume they track the latest version.
        if (! preg_match('/^\d+(\.\d+)*/', $fluxVersion)) {
            return true;
        }

        return version_compare($fluxVersion, $version, '>=');
    }

    // npm update vendor package
    public static function updateVendorPackage(string $vendor, $verbose = false): bool
    {
        $baseConfig = "flux-icons.vendors.{$vendor}";

        if (! config()->has("{$baseConfig}")) {
            throw new \RuntimeException("Vendor {$vendor} not found in config.");
        }

        $package = config("{$baseConfig}.package");
        $arg = $verbose ? '' : '-s';
        exec("npm update {$package} {$arg}", $output, $result);

        if ($result !== 0) {
            throw new \RuntimeException("Failed to update package: {$package}. ".implode("\n", $output));
        }

        return true;
    }
}
