<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Support\Str;
use Ympact\FluxIcons\Services\IconBuilder;
use Illuminate\Console\Command;
use function Laravel\Prompts\{info, select, multisearch, error};

class UpdateFluxIconsCommand extends Command
{
    protected $signature = 'flux-icons:update
                            {--P|vendor : The vendor icon package(s) to update} 
                            {--I|icons : Select the icons to update}';
    protected $description = 'Updates icon vendor packages, built icons and adding @pure directive';

    public function handle()
    {
        $noInteraction = $this->option('no-interaction');
        $verbose = $this->option('verbose');
        
        $vendor = $this->option('vendor') ?? ($noInteraction ? null :
            select(
                label: 'From which vendor do you want to update icons?',
                options: IconBuilder::getAvailableVendors()->keys()->toArray(),
                scroll: 5
            ));
        
        if (!config("flux-icons.vendors.$vendor")) {
            error("Vendor configuration for '$vendor' not found.");
            return 1;
        }

        // update the npm package
        info("Checking if vendor: $vendor is installed");
        $iconBuilder = new IconBuilder($vendor);
        $iconBuilder->setVerbose($verbose)->requirePackage();

        $icons = $this->option('icons') ?? null;
        $all = $this->option('all');
        
        if($icons && $all){
            error("You can't use the --icons option in combination with the --all option");
            return 1;
        }

        $configVendorIcons = config("flux-icons.icons.{$vendor}", null);
        $availableIcons = $iconBuilder->getAvailableIcons();

        if($all || $noInteraction){
            $icons = $all ? $availableIcons->map(function($icon){
                // get the filename without the extension and remove the directory
                return Str::of($icon)->basename('.svg')->toString();
            })->all() : ($icons ? $icons : $configVendorIcons);
        }
        else{
            // adjust select options in case configVendorIcons is set
            $options = [
                'select' => 'Let me choose', 
                'all' => 'All '. $availableIcons->count() . ' icons'
            ];
            $options = $configVendorIcons ? array_merge( ['config' => 'Configured icons for '.$vendor], $options) : $options;

            // if icons is null, confirm that the user wants to build all icons
            if (!$icons) {
                $whichOption = select(
                    label: 'Which icons do you want to build from the vendor?',
                    options: $options,
                    default: $configVendorIcons ? 'config' : 'select'
                );

                if($whichOption === 'config'){
                    $icons = $configVendorIcons;
                }

                if($whichOption === 'select'){
                    $icons = multisearch(
                        label: 'Which icons do you want to build from the vendor?',
                        options: fn (string $value) => $iconBuilder->getAvailableIcons()
                            // remove everything before and including node_modules
                            ->map(fn ($name) => Str::of($name)->after('node_modules/')->toString())
                            ->filter(fn ($name) => Str::contains(Str::of($name)->basename('.svg')->toString(), $value, ignoreCase: true))
                            ->values()
                            ->all(),
                        scroll: 10
                    );
                    
                    // if none selected, notify and exit
                    if(empty($icons)){
                        $this->error("You did not select any icons. Exiting...");
                        return 1;
                    }

                    // only get the file name of the icons
                    $icons = array_map(function($icon){
                        return Str::of($icon)->basename('.svg')->toString();
                    }, $icons);
                }
            }
        }


        info("Start updating icons");
        $iconBuilder->setIcons($icons);
        
        info("Updating icons for vendor: $vendor");
        $iconBuilder->buildIcons();

        info("Icons updated successfully for vendor: $vendor");
        return 0;
    }
}