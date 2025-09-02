<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Support\Str;
use Ympact\FluxIcons\Services\IconBuilder;
use Illuminate\Console\Command;
use Ympact\FluxIcons\Services\IconManager;
use Ympact\FluxIcons\Services\PackageManager;

use function Laravel\Prompts\{info, error};

class UpdateFluxIconsCommand extends Command
{
    protected $signature = 'flux-icons:update
                            {--P|vendor : The vendors for which to update the icon package}';
    protected $description = 'Updates icon vendor packages, built icons and adding @pure directive';

    public function handle()
    {
        $verbose = $this->option('verbose');

        $vendors = $this->option('vendor') 
            ? IconManager::currentVendors()->slice($this->option('vendor')) 
            : IconManager::currentVendors();

        $installedIcons = IconManager::installedIcons($vendors);

        info("Updating the packages");
        $installedIcons->each(function($vendorIcons, $vendor) use ($verbose){
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
            $this->components->task(
                'Updating '.count($vendorIcons).' icons for '.$vendor,
                function() use ($vendorIcons, $vendor, $verbose){
                    try {
                        $iconBuilder = new IconBuilder($vendor);
                        $iconBuilder->setVerbose($verbose)->requirePackage();
                        $iconBuilder->setIcons($vendorIcons->all());
                        $iconBuilder->buildIcons();
                        return true;
                    } catch (\RuntimeException $e) {
                        error("Failed to update the icons for: $vendor. ".$e->getMessage());
                        return false;
                    }
                }
            );

        });

        return 0;
    }
}