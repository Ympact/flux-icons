<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Facades\File;
use Ympact\FluxIcons\Contracts\Vendor;

/**
 * Reads the installed Flux version from composer.lock, and installs and updates the npm
 * packages vendors draw their icons from.
 */
class PackageManager
{
    /**
     * The livewire/flux entry of the application's composer.lock: null when there is no lock
     * or it does not list Flux.
     *
     * @return array<string, mixed>|null
     */
    public static function fluxPackage(): ?array
    {
        $lock = base_path('composer.lock');

        if (! File::exists($lock)) {
            return null;
        }

        $packages = (array) (json_decode((string) file_get_contents($lock), true)['packages'] ?? []);
        $flux = collect($packages)->firstWhere('name', 'livewire/flux');

        return is_array($flux) ? $flux : null;
    }

    public static function fluxVersion(): ?string
    {
        $version = static::fluxPackage()['version'] ?? null;

        return is_string($version) ? $version : null;
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

    /**
     * Install the vendor's npm package unless node_modules already holds it.
     *
     * @return bool Whether the package had to be installed.
     *
     * @throws \RuntimeException When npm fails to install it.
     */
    public static function requirePackage(Vendor $vendor, bool $verbose = false): bool
    {
        $package = $vendor->package();

        if (File::exists(base_path("node_modules/{$package}/package.json"))) {
            return false;
        }

        static::npm('install', $package, $verbose, '--save');

        return true;
    }

    /**
     * @throws \RuntimeException When npm fails to update the package.
     */
    public static function updateVendorPackage(Vendor $vendor, bool $verbose = false): bool
    {
        static::npm('update', $vendor->package(), $verbose);

        return true;
    }

    /**
     * Replaced by an NpmManager with #23.
     *
     * @throws \RuntimeException
     */
    protected static function npm(string $action, string $package, bool $verbose, string ...$flags): void
    {
        if (! $verbose) {
            $flags[] = '-s';
        }

        exec("npm {$action} ".escapeshellarg($package).' '.implode(' ', $flags).' 2>&1', $output, $result);

        if ($result !== 0) {
            throw new \RuntimeException("npm could not {$action} {$package}: ".implode("\n", $output));
        }
    }
}
