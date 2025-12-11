<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\ConsoleOutput;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class PackageManager
{
    public function __construct(
        protected ConsoleOutput $output
    ) {}

    /**
     * get Livewire\Flux version
     */
    public function fluxVersion()
    {
        $composerFile = base_path('composer.lock');

        if (! File::exists($composerFile)) {
            error("composer.lock not found. Can't determine Livewire\Flux version.");

            return null;
        }
        $composerLock = json_decode(file_get_contents($composerFile), true);
        $packages = collect($composerLock['packages']);
        $fluxPackage = $packages->firstWhere('name', 'livewire/flux');

        return $fluxPackage['version'] ?? null;
    }

    /**
     * install vendor package
     *
     * @method requirePackage
     *
     * @return void
     *
     * @todo create tests for this method
     */
    public function requirePackage(?string $vendor = null, bool $verbose = false)
    {
        $vendor = $vendor ?? config('defaults.vendor');
        $packageName = config("{$vendor}.package");
        // check if package is not yet installed using package-lock.json
        $packageFile = base_path('package-lock.json');
        if (! File::exists($packageFile)) {
            info('package-lock.json not found. Running npm install');
            exec('npm install');
        }
        $packageLock = json_decode(File::get($packageFile), true);
        $packages = collect($packageLock['packages']);

        if (! ($packages->has('node_modules/'.$packageName) && File::exists('node_modules/'.$packageName.'/package.json'))) {
            info("Package not found. Installing package $packageName");
            // in case !$verbose keep npm install silent
            $arg = $verbose ? '' : '-s';
            exec("npm install $packageName --save {$arg}", $output, $result);

            if ($result == 128) {
                $verbose ? $this->output->writeln("<error>Failed to install package $packageName. Please check if it is set correctly in the config.</error>") : null;
                // finish the process
                exit(1);
            }
        } else {
            $verbose ? $this->output->writeln("<info>Package $packageName already installed</info>") : null;
        }

        return $this;
    }

    /**
     * update vendor package
     *
     * @method updatePackage($vendor = null)
     *
     * @return void
     */
    public function updatePackage($vendor = null)
    {
        $packageName = config("{$vendor}.package");

        $verbose ? $this->output->writeln("<info>Updating package $packageName</info>") : null;

        exec("npm update $packageName", $output, $result);

        if ($result !== 0) {
            throw new \RuntimeException("Failed to update package $packageName");
        }

        return true;
    }
}
