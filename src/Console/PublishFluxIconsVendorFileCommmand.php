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

        $className = Str::studly($vendor);
        $file = $className . '.php';
        $sourcePath = __DIR__ . '/../Services/Vendors/' . $file;
        $destinationPath = app_path('Services/FluxIcons/Vendors/' . $file);

        if (!file_exists($sourcePath)) {
            $this->error("The file {$file} does not exist in the source directory.");
            return 1;
        }

        if (!is_dir(dirname($destinationPath))) {
            mkdir(dirname($destinationPath), 0755, true);
        }

        copy($sourcePath, $destinationPath);

        // change the namespace in the file
        $content = file_get_contents($destinationPath);
        $oldNamespace = 'Ympact\FluxIcons\Services\Vendors';
        $newNamespace = 'App\Services\FluxIcons\Vendors';

        $content = str_replace($oldNamespace, $newNamespace, $content);
        file_put_contents($destinationPath, $content);

        $this->info("The file {$file} has been published to {$destinationPath}.");
        
        // check if config file was published
        if (!file_exists(config_path('flux-icons.php'))) {
            if ($this->confirm("The configuration file has not been published yet. You need it to use the vendor file. Do you want to publish it now?")) {
                $this->info(" We will publish it now.");
                $this->call('vendor:publish', ['--tag' => 'flux-icons-config']); 
            }
        }

        if ($this->confirm("To use the vendor file, you will need to adjust the namespace in the config file. Should we do that for you?")) {
            // replace the namespace in the config file
            $configPath = config_path('flux-icons.php');
            $content = file_get_contents($configPath);
            $content = str_replace(
                Str::of($oldNamespace)->finish('\\'.$className)->toString(), 
                Str::of($newNamespace)->finish('\\'.$className)->toString(), 
                $content
            );
            file_put_contents($configPath, $content);
        }
        
        return 0;
    }
}