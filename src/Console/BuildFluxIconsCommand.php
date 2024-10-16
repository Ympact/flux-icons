<?php

namespace Ympact\FluxIcons\Console;

use Ympact\FluxIcons\Services\IconBuilder;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;

class BuildFluxIconsCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'flux-icons:build
                             {vendor? : The vendor icon package to use} 
                             {--icons= : The icons to build (single or comma separated list)}
                             {-a|--all : All icons from the vendor}
                             {-m|--merge : Merge the icons from the --icons option with the default icons}
                             {-v|--verbose : Show more details about the build process}';
    protected $description = 'Build icons for Flux using a specific icon package';

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array<string, string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'vendor' => 'Which vendor icon package should be used?',
        ];
    }

    public function handle()
    {
        $vendor = $this->argument('vendor') ?? $this->ask('Which vendor icon package should be used (options: '.implode(', ',IconBuilder::getAvailableVendors()).')?');
        $icons = $this->option('icons') ?? null;
        $all = $this->option('all');
        
        if($icons && $all){
            $this->error("You can't use the --icons option with the --all option");
            return 1;
        }

        $defaultIcons = config("flux-icons.icons.{$vendor}", null);

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

        // if icons is still null , confirm that the user wants to build all icons
        if (!$icons && !$this->confirm("Are you sure you want to build all icons for vendor: $vendor?")) {
            $icons = $this->ask('Which icons should be built? (comma separated list)');
        }

        if (!config("flux-icons.$vendor")) {
            $this->error("Vendor configuration for '$vendor' not found.");
            return 1;
        }

        // in case no icons are defined, use config("{$this->config}.icons")

        $files = app(Filesystem::class);
        $verbose = $this->option('verbose');
        $iconBuilder = new IconBuilder($vendor, $files, $icons, $verbose);

        $this->info("Installing package for vendor: $vendor");
        $iconBuilder->installPackage();

        $this->info("Building icons for vendor: $vendor");
        $iconBuilder->buildIcons();

        $this->info("Icons built successfully for vendor: $vendor");
        return 0;
    }
}