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
                            {--P|vendor : The vendors for which to update the icon package}';

    protected $description = 'Updates icon vendor packages, built icons and adding @blaze directive';

    public function handle()
    {
        $verbose = $this->option('verbose');

        $vendors = $this->option('vendor')
            ? IconManager::currentVendors()->slice($this->option('vendor'))
            : IconManager::currentVendors();

        $installedIcons = IconManager::installedIcons($vendors);

        info('Updating the packages');
        $installedIcons->each(function ($vendorIcons, $vendor) use ($verbose) {
            // update the npm packages
            $this->components->task(
                'Updating package '.$vendor,
                function () use ($vendor, $verbose) {
                    try {
                        return PackageManager::updateVendorPackage($vendor, $verbose);
                    } catch (\RuntimeException $e) {
                        error("Failed to update the package for: $vendor. ".$e->getMessage());

                        return false;
                    }
                }
            );

            // update the icons
            info('Updating '.count($vendorIcons).' icons for '.$vendor);

            try {
                $iconBuilder = new IconBuilder($vendor);
                $iconBuilder->setVerbose($verbose)->requirePackage();
                $iconBuilder->setIcons($vendorIcons->all());
                $iconBuilder->buildIcons();

            } catch (\RuntimeException $e) {
                error("Failed to update the icons for: $vendor. ".$e->getMessage());
            }

        });

        return 0;
    }
}
