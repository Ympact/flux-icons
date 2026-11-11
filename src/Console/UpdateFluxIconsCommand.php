<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Ympact\FluxIcons\Build\Builder;
use Ympact\FluxIcons\Console\Concerns\ReportsBuildResult;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Services\IconManager;
use Ympact\FluxIcons\Services\NpmManager;
use Ympact\FluxIcons\Support\VendorRegistry;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

/**
 * Updates the npm package of every vendor with built icons, and rebuilds those icons.
 */
class UpdateFluxIconsCommand extends Command
{
    use ReportsBuildResult;

    protected $signature = 'flux-icons:update
                            {--P|vendor= : The vendors for which to update the icon package (single or comma separated list)}';

    protected $description = 'Updates icon vendor packages, built icons and adding @blaze directive';

    public function handle(): int
    {
        $verbose = (bool) $this->option('verbose');

        try {
            $registry = VendorRegistry::fromConfig();
        } catch (InvalidArgumentException $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        /** @var list<string> $directories */
        $directories = IconManager::currentVendors()->values()->all();
        $option = $this->option('vendor');

        if (is_string($option) && $option !== '') {
            $directories = $this->onlyRequested($registry, $directories, $option);

            if ($directories === []) {
                error('None of the requested vendors have built icons. Nothing to update.');

                return self::FAILURE;
            }
        }

        // resolved like the build command's, so an application that binds the builder to another
        // node_modules updates from there too
        $builder = $this->laravel->make(Builder::class);
        $npm = $this->laravel->make(NpmManager::class);
        $failed = [];

        info('Updating the packages');

        foreach (IconManager::installedIcons(collect($directories)) as $directory => $icons) {
            // built icons live in a directory named after the vendor's namespace, which is not
            // necessarily the key the vendor is registered under
            $vendor = $registry->findByNamespace((string) $directory);

            if ($vendor === null) {
                $verbose ? info("Skipping {$directory}, it does not belong to a configured vendor.") : null;

                continue;
            }

            $updated = false;
            $this->components->task(
                "Updating package {$vendor->key()}",
                function () use ($npm, $vendor, $verbose, &$updated) {
                    return $updated = $this->updatePackage($npm, $vendor, $verbose);
                }
            );

            if (! $updated) {
                $failed[] = $vendor->key();
            }

            info('Updating '.count($icons)." icons for {$vendor->key()}");

            try {
                $npm->require($vendor, $verbose);
                // a component whose icon left the package is reported as not found, and kept
                $this->report($builder->build($vendor, $icons->all()), $verbose);
            } catch (\RuntimeException $exception) {
                error("Failed to update the icons for: {$vendor->key()}. ".$exception->getMessage());
                $failed[] = $vendor->key();
            }
        }

        if ($failed !== []) {
            error('The update failed for: '.implode(', ', array_unique($failed)).'.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Update the vendor's npm package, saying why when that fails.
     */
    protected function updatePackage(NpmManager $npm, Vendor $vendor, bool $verbose): bool
    {
        try {
            $npm->update($vendor, $verbose);

            return true;
        } catch (\RuntimeException $exception) {
            error("Failed to update the package for: {$vendor->key()}. ".$exception->getMessage());

            return false;
        }
    }

    /**
     * The built vendor directories asked for by directory or by vendor key; reports names matching none.
     *
     * @param  list<string>  $directories
     * @return list<string>
     */
    protected function onlyRequested(VendorRegistry $registry, array $directories, string $option): array
    {
        $requested = array_values(array_filter(array_map('trim', explode(',', $option))));
        $keys = [];

        foreach ($directories as $directory) {
            $keys[$directory] = $registry->findByNamespace($directory)?->key();
        }

        $matched = array_values(array_filter(
            $directories,
            fn (string $directory) => in_array($directory, $requested, true) || in_array($keys[$directory], $requested, true)
        ));

        $unknown = array_diff($requested, $directories, array_filter($keys));

        if ($unknown !== []) {
            error('No built icons found for: '.implode(', ', $unknown));
        }

        return $matched;
    }
}
