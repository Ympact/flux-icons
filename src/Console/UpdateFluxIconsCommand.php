<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Console\Command;
use Ympact\FluxIcons\Services\IconBuilder;
use Ympact\FluxIcons\Services\IconManager;
use Ympact\FluxIcons\Services\PackageManager;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class UpdateFluxIconsCommand extends Command
{
    protected $signature = 'flux-icons:update
                            {--P|vendor= : The vendors for which to update the icon package (single or comma separated list)}';

    protected $description = 'Updates icon vendor packages, built icons and adding @blaze directive';

    public function handle()
    {
        $verbose = $this->option('verbose');

        $currentVendors = IconManager::currentVendors();

        $vendors = $currentVendors;

        if ($vendorOption = $this->option('vendor')) {
            $requested = collect(is_array($vendorOption) ? $vendorOption : explode(',', (string) $vendorOption))
                ->map(fn ($vendor) => trim((string) $vendor))
                ->filter();

            // accept both the config key and the directory the icons were built into
            $keysByDirectory = $currentVendors->mapWithKeys(
                fn ($directory) => [$directory => IconManager::resolveVendorKey($directory)]
            );

            $vendors = $currentVendors->filter(
                fn ($directory) => $requested->contains($directory)
                    || $requested->contains($keysByDirectory->get($directory))
            )->values();

            $unknown = $requested->reject(
                fn ($name) => $currentVendors->contains($name) || $keysByDirectory->contains($name)
            )->all();

            if ($unknown) {
                error('No built icons found for: '.implode(', ', $unknown));
            }

            if ($vendors->isEmpty()) {
                error('None of the requested vendors have built icons. Nothing to update.');

                return 1;
            }
        }

        $installedIcons = IconManager::installedIcons($vendors);

        info('Updating the packages');
        $installedIcons->each(function ($vendorIcons, $vendor) use ($verbose) {
            // built icons live in a directory named after the vendor's namespace,
            // which is not necessarily the key used in the config
            $vendorKey = IconManager::resolveVendorKey($vendor);

            if (! $vendorKey) {
                $verbose ? info("Skipping $vendor, it does not belong to a configured vendor.") : null;

                return;
            }

            // update the npm packages
            $this->components->task(
                'Updating package '.$vendorKey,
                function () use ($vendorKey, $verbose) {
                    try {
                        return PackageManager::updateVendorPackage($vendorKey, $verbose);
                    } catch (\RuntimeException $e) {
                        error("Failed to update the package for: $vendorKey. ".$e->getMessage());

                        return false;
                    }
                }
            );

            // update the icons
            info('Updating '.count($vendorIcons).' icons for '.$vendorKey);

            try {
                $iconBuilder = new IconBuilder($vendorKey);
                $iconBuilder->setVerbose($verbose)->requirePackage();
                $iconBuilder->setIcons($vendorIcons->all());
                $iconBuilder->buildIcons();

            } catch (\Throwable $e) {
                error("Failed to update the icons for: $vendorKey. ".$e->getMessage());
            }

        });

        return 0;
    }
}
