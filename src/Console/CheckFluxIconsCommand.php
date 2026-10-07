<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;
use Ympact\FluxIcons\Services\IconManager;
use Ympact\FluxIcons\Services\IconReference;
use Ympact\FluxIcons\Services\IconReferenceScanner;

/**
 * Reports icons that templates ask for but that were never built.
 *
 * Flux throws when an icon component does not exist, so such a reference is a 500 on
 * whichever page renders it, and nothing surfaces it until that page is rendered. Built
 * icons are files, so whether a reference resolves is a static question — this answers it
 * without running the application.
 */
class CheckFluxIconsCommand extends Command
{
    protected $signature = 'flux-icons:check
                            {vendor? : Only check icons of this vendor, given by its config key}
                            {--P|path=* : Directories to scan (defaults to the registered view paths)}
                            {--build : Print the build command for the missing icons}';

    protected $description = 'Check that every icon referenced in a template has been built';

    public function handle(): int
    {
        $vendors = $this->vendors();

        if ($vendors->isEmpty()) {
            $this->components->error('No vendors are configured, so there is nothing to check.');

            return self::FAILURE;
        }

        $vendor = $this->argument('vendor');

        if (is_string($vendor) && $vendor !== '') {
            if (! $vendors->has($vendor)) {
                $this->components->error("Vendor configuration for '{$vendor}' not found.");
                $this->line('  Configured vendors: '.$vendors->keys()->sort()->implode(', '));

                return self::FAILURE;
            }

            $vendors = $vendors->only([$vendor]);
        }

        $paths = $this->paths();

        if ($paths === []) {
            $this->components->error('No directories to scan.');

            return self::FAILURE;
        }

        $references = (new IconReferenceScanner($this->namespaces($vendors)))->scan($paths);
        $unresolved = $references->reject(fn (IconReference $reference) => $reference->exists());

        if ($unresolved->isEmpty()) {
            $this->components->info(
                "All {$references->count()} icon references resolve to a built icon."
            );

            return self::SUCCESS;
        }

        $this->report($unresolved);

        if ($this->option('build')) {
            $this->suggestBuild($unresolved);
        }

        return self::FAILURE;
    }

    /**
     * @param  Collection<int, IconReference>  $unresolved
     */
    protected function report(Collection $unresolved): void
    {
        $this->newLine();

        foreach ($unresolved->groupBy(fn (IconReference $reference) => $reference->relativeFile()) as $file => $inFile) {
            $this->line("  <fg=yellow>{$file}</>");

            foreach ($inFile as $reference) {
                $reason = $reference->isMalformed()
                    ? 'is not a valid icon name'
                    : 'has not been built';

                $this->line("    <fg=gray>{$reference->line}</>  <fg=red>{$reference->name()}</> {$reason}");
            }

            $this->newLine();
        }

        $this->components->error(
            $unresolved->count().' icon '.
            ($unresolved->count() === 1 ? 'reference does' : 'references do').
            ' not resolve.'
        );
    }

    /**
     * @param  Collection<int, IconReference>  $unresolved
     */
    protected function suggestBuild(Collection $unresolved): void
    {
        $buildable = $unresolved->reject(fn (IconReference $reference) => $reference->isMalformed());

        if ($buildable->isEmpty()) {
            return;
        }

        foreach ($buildable->groupBy(fn (IconReference $reference) => $reference->namespace) as $namespace => $forVendor) {
            $icons = $forVendor->map(fn (IconReference $reference) => $reference->icon)->unique()->sort()->implode(',');

            // flux-icons:build takes the config key, which is not always the namespace
            $key = IconManager::resolveVendorKey($namespace) ?? $namespace;

            $this->line("  php artisan flux-icons:build {$key} --icons={$icons} --merge");
        }

        $this->newLine();
    }

    /**
     * The configured vendors, keyed by config key.
     *
     * @return Collection<array-key, mixed>
     */
    protected function vendors(): Collection
    {
        $configured = config('flux-icons.vendors', []);

        return collect(is_array($configured) ? $configured : []);
    }

    /**
     * The namespace each vendor publishes its icons under: its config key unless overridden.
     *
     * @param  Collection<array-key, mixed>  $vendors
     * @return Collection<int, string>
     */
    protected function namespaces(Collection $vendors): Collection
    {
        return $vendors
            ->map(fn ($config, $key) => is_array($config) && isset($config['namespace'])
                ? (string) $config['namespace']
                : (string) $key)
            ->values();
    }

    /**
     * Where to look.
     *
     * Defaults to every registered view path rather than just resources/views, so templates
     * that live in a package or a module are covered too.
     *
     * @return array<int, string>
     */
    protected function paths(): array
    {
        $given = $this->option('path');

        if (is_array($given) && $given !== []) {
            return array_values(array_map('strval', $given));
        }

        $finder = View::getFinder();

        // Only the file based finder knows about directories; anything else (a database
        // backed finder, say) has nothing to walk, so fall back to the conventional path.
        if (! $finder instanceof FileViewFinder) {
            return [resource_path('views')];
        }

        $paths = $finder->getPaths();

        foreach ($finder->getHints() as $namespacePaths) {
            $paths = array_merge($paths, $namespacePaths);
        }

        return array_values(array_unique(array_map('strval', $paths)));
    }
}
