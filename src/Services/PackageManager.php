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

        // Branch versions (dev-main, dev-master, 2.x-dev, ...) cannot be compared
        // reliably, assume they track the latest release. Note that a leading-digit
        // check alone is not enough: version_compare() ranks the "x" of 2.x-dev below
        // any number, which would place it below every release.
        if (str_contains(strtolower($fluxVersion), 'dev') || ! preg_match('/^\d/', $fluxVersion)) {
            return true;
        }

        // pre-releases are left to version_compare(), which orders them correctly
        // against a release (2.0.0-beta.1 < 2.2.6 < 2.13.1-beta.1)
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
