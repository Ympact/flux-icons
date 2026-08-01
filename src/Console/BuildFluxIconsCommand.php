<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Services\IconBuilder;

use function Laravel\Prompts\multisearch;
use function Laravel\Prompts\select;

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
        $noInteraction = $this->option('no-interaction');
        $verbose = $this->option('verbose');

        $vendor = $this->argument('vendor') ?? ($noInteraction ? null :
            select(
                label: 'From which vendor do you want to build icons?',
                options: IconBuilder::getAvailableVendors()->keys()->sort()->toArray(),
                scroll: 5
            ));

        if (! config("flux-icons.vendors.$vendor")) {
            $this->error("Vendor configuration for '$vendor' not found.");

            return 1;
        }

        // in case the vendor is not yet installed, install it
        $this->info("Checking if vendor: $vendor is installed");
        $iconBuilder = new IconBuilder($vendor);
        $iconBuilder->setVerbose($verbose)->requirePackage();

        $icons = $this->option('icons') ?? null;
        $all = $this->option('all');
        $merge = $this->option('merge');

        if ($icons && $all) {
            $this->error("You can't use the --icons option in combination with the --all option");

            return 1;
        }

        if ($merge && $all) {
            $this->error("You can't use the --merge option in combination with the --all option");

            return 1;
        }

        $configVendorIcons = config("flux-icons.icons.{$vendor}", null);
        $availableIcons = $iconBuilder->getAvailableIcons();

        if ($all || $noInteraction) {
            $icons = $all ? $availableIcons->map(function ($icon) {
                // get the filename without the extension and remove the directory
                return Str::of($icon)->basename('.svg')->toString();
            })->all() : ($icons ? $icons : $configVendorIcons);
        } else {
            // adjust select options in case configVendorIcons is set
            $options = [
                'select' => 'Let me choose',
                'all' => 'All '.$availableIcons->count().' icons',
            ];
            $options = $configVendorIcons ? array_merge(['config' => 'Configured icons for '.$vendor], $options) : $options;

            // if icons is null, confirm that the user wants to build all icons
            if (! $icons) {
                $whichOption = select(
                    label: 'Which icons do you want to build from the vendor?',
                    options: $options,
                    default: $configVendorIcons ? 'config' : 'select'
                );

                if ($whichOption === 'config') {
                    $icons = $configVendorIcons;
                }

                if ($whichOption === 'select') {
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
                    if (empty($icons)) {
                        $this->error('You did not select any icons. Exiting...');

                        return 1;
                    }

                    // only get the file name of the icons
                    $icons = array_map(function ($icon) {
                        return Str::of($icon)->basename('.svg')->toString();
                    }, $icons);
                }
            }
        }

        if ($merge) {
            $icons = $this->mergeWithConfiguredIcons($icons, $configVendorIcons);
        }

        $this->info('Start building icons 👀');
        $iconBuilder->setIcons($icons);

        $this->info("Building icons for vendor: $vendor");
        $iconBuilder->buildIcons();

        $this->newLine()->info("Icons built successfully for vendor: $vendor");

        return 0;
    }

    /**
     * Combine the requested icons with the icons configured for the vendor.
     *
     * @param  string|array<int,string>|null  $icons
     * @param  array<int,string>|null  $configVendorIcons
     * @return array<int,string>
     */
    protected function mergeWithConfiguredIcons($icons, $configVendorIcons): array
    {
        return collect($this->normalizeIcons($icons))
            ->merge($this->normalizeIcons($configVendorIcons))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Accept both a comma separated list and an array of icon names.
     *
     * @param  string|array<int,string>|null  $icons
     * @return array<int,string>
     */
    protected function normalizeIcons($icons): array
    {
        if (blank($icons)) {
            return [];
        }

        return collect(is_string($icons) ? explode(',', $icons) : $icons)
            ->map(fn ($icon) => trim((string) $icon))
            ->filter()
            ->values()
            ->all();
    }
}
