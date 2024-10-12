<?php

namespace FluxIcons\Console;

use FluxIcons\Services\IconBuilder;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class BuildFluxIconsCommand extends Command
{
    protected $signature = 'flux-icons:build {vendor?}';
    protected $description = 'Build icons for Flux using a specific icon package';

    public function handle()
    {
        $vendor = $this->argument('vendor') ?? $this->ask('Which vendor icon package should be used?');

        if (!config("flux-icons.$vendor")) {
            $this->error("Vendor configuration for '$vendor' not found.");
            return 1;
        }
        $files = app(Filesystem::class);
        $iconBuilder = new IconBuilder($vendor, $files);

        $this->info("Installing package for vendor: $vendor");
        $iconBuilder->installPackage();

        $this->info("Building icons for vendor: $vendor");
        $iconBuilder->buildIcons();

        $this->info("Icons built successfully for vendor: $vendor");
        return 0;
    }
}