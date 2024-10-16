<?php

namespace Ympact\FluxIcons\Console;

use Ympact\FluxIcons\Services\IconBuilder;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class BuildFluxIconsCommand extends Command
{
    protected $signature = 'flux-icons:build
                            {vendor? : The vendor icon package to use} 
                            {--I|icons= : The icons to build (single or comma separated list)}
                            {--A|all : All icons from the vendor}
                            {--M|merge : Merge the icons from the --icons option with the default icons}';
    protected $description = 'Build icons for Flux using a specific icon package';

    public function handle()
    {
        $vendor = $this->argument('vendor') ?? $this->ask('Which vendor icon package should be used (options: '.implode(', ',IconBuilder::getAvailableVendors()).')?');
        
        if (!config("flux-icons.vendors.$vendor")) {
            $this->error("Vendor configuration for '$vendor' not found.");
            return 1;
        }

        $icons = $this->option('icons') ?? null;
        $all = $this->option('all');
        
        if($icons && $all){
            $this->error("You can't use the --icons option with the --all option");
            return 1;
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

        // if icons is still null, confirm that the user wants to build all icons
        if (!$icons && !$this->confirm("Are you sure you want to build all icons for vendor: $vendor?")) {
            $icons = $this->ask('Which icons should be built? (comma separated list)');
            $icons = explode(',', $icons);
        }

        $verbose = $this->option('verbose');
        $iconBuilder = new IconBuilder($vendor, $icons, $verbose);

        $this->info("Installing package for vendor: $vendor");
        $iconBuilder->installPackage();
        
        $this->info("Building icons for vendor: $vendor");
        $iconBuilder->buildIcons();

        $this->newLine()->info("Icons built successfully for vendor: $vendor");
        return 0;
    }
}