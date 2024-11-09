<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Support\Str;
use Ympact\FluxIcons\Services\IconBuilder;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use function Laravel\Prompts\select;

class BuildFluxIconsCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'flux-icons:build
                            {vendor? : The vendor icon package to use} 
                            {--I|icons= : The icons to build (single or comma separated list)}
                            {--A|all : All icons from the vendor}
                            {--M|merge : Merge the icons from the --icons option with the default icons}';
    protected $description = 'Build icons for Flux using a specific icon package';


    public function handle()
    {
        $verbose = $this->option('verbose');
        
        $vendor = $this->argument('vendor') ?? 
            $this->select(
                label: 'From which vendor do you want to build icons?',
                options: IconBuilder::getAvailableVendors()->keys()->toArray(),
                scroll: 5
            );
        
        if (!config("flux-icons.vendors.$vendor")) {
            $this->error("Vendor configuration for '$vendor' not found.");
            return 1;
        }

        // in case the vendor is not yet installed, install it
        $this->info("Checking if vendor: $vendor is installed");
        $iconBuilder = new IconBuilder($vendor);
        $iconBuilder->setVerbose($verbose)->requirePackage();

        $icons = $this->option('icons') ?? null;
        $all = $this->option('all');
        
        if($icons && $all){
            $this->error("You can't use the --icons option in combination with the --all option");
            return 1;
        }

        $availableIcons = $iconBuilder->getAvailableIcons();
        // if icons is null, confirm that the user wants to build all icons
        if (!$icons) {
            $whichOption = $this->select(
                label: 'Which icons do you want to build from the vendor?',
                options: [
                    'select' => 'Let me choose', 
                    'all' => 'All '. $availableIcons->count() . ' icons'],
                default: 'select'
            );

            $all = $whichOption === 'all';    

        }

        if(!$all && !$icons){
            $icons = $this->multisearch(
                label: 'Which icons do you want to build from the vendor?',
                options: fn (string $value) => $iconBuilder->getAvailableIcons()
                    ->filter(fn ($name) => Str::contains($name, $value, ignoreCase: true))
                    ->values()
                    ->all(),
                scroll: 10
            ); 
        }

        $defaultIcons = config("flux-icons.icons", null);

        if($icons){
            $icons = explode(',', $icons);
            if($this->option('merge')){
                if($defaultIcons){
                    $icons = array_merge($defaultIcons, $icons);
                }
            }   
        }
        else{
            $icons = $defaultIcons;
        }

        $this->info("Start building icons 👀");
        $iconBuilder->setIcons($icons);
        
        $this->info("Building icons for vendor: $vendor");
        $iconBuilder->buildIcons();

        $this->newLine()->info("Icons built successfully for vendor: $vendor");
        return 0;
    }
}