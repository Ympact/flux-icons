<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Services\IconBuilder;

class PublishFluxIconsVendorFileCommmand extends Command
{
    protected $signature = 'flux-icons:publish {vendor?}';
    protected $description = 'Publish a specific Services/Vendor file';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $vendor = $this->argument('vendor') ?? $this->ask('Which vendor icon package should be used (options: '.implode(', ',IconBuilder::getAvailableVendors()).')?');
        
        if (!config("flux-icons.vendors.$vendor")) {
            $this->error("Vendor configuration for '$vendor' not found.");
            return 1;
        }

        $file = Str::studly($vendor) . '.php';
        $sourcePath = __DIR__ . '/../../Services/Vendors/' . $file;
        $destinationPath = app_path('Services/FluxIcons/Vendors/' . $file);

        if (!file_exists($sourcePath)) {
            $this->error("The file {$file} does not exist in the source directory.");
            return 1;
        }

        if (!is_dir(dirname($destinationPath))) {
            mkdir(dirname($destinationPath), 0755, true);
        }

        copy($sourcePath, $destinationPath);
        $this->info("The file {$file} has been published to {$destinationPath}.");

        return 0;
    }
}