<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;
use InvalidArgumentException;
use Ympact\FluxIcons\Build\Builder;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Services\DeclaredIcons;
use Ympact\FluxIcons\Services\IconReference;
use Ympact\FluxIcons\Services\IconReferenceScanner;
use Ympact\FluxIcons\Support\VendorRegistry;

/**
 * Reports icons that templates and PHP sources ask for, or installed packages declare they
 * need, but that were never built.
 *
 * Flux throws when an icon component does not exist, so such a reference is a 500 on
 * whichever page renders it, and nothing surfaces it until that page is rendered. Built
 * icons are files, so whether a reference resolves is a static question. This answers it
 * without running the application.
 */
class CheckFluxIconsCommand extends Command
{
    protected $signature = 'flux-icons:check
                            {vendor? : Only check icons of this vendor, given by its config key}
                            {--P|path=* : Directories to scan (defaults to the registered view paths, app and config)}
                            {--build : Print the build command for the missing icons}
                            {--json : Print the result as JSON}';

    protected $description = 'Check that every icon referenced in a template or PHP source has been built';

    public function handle(): int
    {
        try {
            $registry = VendorRegistry::fromConfig();
        } catch (InvalidArgumentException $exception) {
            return $this->failWith($exception->getMessage());
        }

        $vendors = $registry->all();

        if ($vendors->isEmpty()) {
            return $this->failWith('No vendors are configured, so there is nothing to check.');
        }

        $vendor = $this->argument('vendor');

        if (is_string($vendor) && $vendor !== '') {
            if (! $vendors->has($vendor)) {
                return $this->failWith(
                    "Vendor configuration for '{$vendor}' not found.",
                    'Configured vendors: '.$vendors->keys()->sort()->implode(', ')
                );
            }

            $vendors = $vendors->only([$vendor]);
        }

        $paths = $this->paths();

        if ($paths === []) {
            return $this->failWith('No directories to scan.');
        }

        $namespaces = $vendors->map(fn (Vendor $vendor) => $vendor->namespace())->values();
        // resolved like the build command's, so the check looks where the icons are built
        $iconsPath = $this->laravel->make(Builder::class)->outputPath();
        $references = (new IconReferenceScanner($namespaces, $iconsPath))->scan($paths)
            ->merge($this->declared($registry, array_values($namespaces->all()), filled($this->argument('vendor')), $iconsPath))
            ->values();
        $unresolved = $references->filter(fn (IconReference $reference) => $reference->reason() !== null)->values();

        if ($this->option('json')) {
            $this->line((string) json_encode($this->toJson($references, $unresolved, $registry), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $unresolved->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        if ($unresolved->isEmpty()) {
            $this->components->info(
                "All {$references->count()} icon references resolve to a built icon."
            );

            return self::SUCCESS;
        }

        $this->report($unresolved);

        if ($this->option('build')) {
            $this->suggestBuild($unresolved, $registry);
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
                $reason = match ($reference->reason()) {
                    IconReference::MALFORMED => 'is not a valid icon name',
                    IconReference::UNKNOWN_VENDOR => "has no configured vendor publishing to {$reference->namespace}",
                    IconReference::UNKNOWN_VARIANT => "is not built with the {$reference->variant} variant, so it draws its default one",
                    default => 'has not been built',
                };

                $where = $reference->package !== null ? 'needs' : $reference->line;

                $this->line("    <fg=gray>{$where}</>  <fg=red>{$reference->name()}</> {$reason}");
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
    protected function suggestBuild(Collection $unresolved, VendorRegistry $registry): void
    {
        $commands = $this->buildCommands($unresolved, $registry);

        if ($commands === []) {
            return;
        }

        foreach ($commands as $command) {
            $this->line("  {$command['command']}");
        }

        $this->newLine();
    }

    /**
     * The build command for the icons that are merely missing, per vendor.
     *
     * @param  Collection<int, IconReference>  $unresolved
     * @return list<array{vendor: string, icons: list<string>, command: string}>
     */
    protected function buildCommands(Collection $unresolved, VendorRegistry $registry): array
    {
        $commands = [];
        $missing = $unresolved->filter(fn (IconReference $reference) => $reference->reason() === IconReference::NOT_BUILT);

        foreach ($missing->groupBy(fn (IconReference $reference) => $reference->namespace) as $namespace => $forVendor) {
            $icons = array_values($forVendor->map(fn (IconReference $reference) => $reference->icon)->unique()->sort()->all());
            $key = $this->vendorKey($registry, (string) $namespace);

            $commands[] = [
                'vendor' => $key,
                'icons' => $icons,
                'command' => "php artisan flux-icons:build {$key} --icons=".implode(',', $icons).' --merge',
            ];
        }

        return $commands;
    }

    /**
     * flux-icons:build takes the config key, which is not always the namespace.
     */
    protected function vendorKey(VendorRegistry $registry, string $namespace): string
    {
        return $registry->findByNamespace($namespace)?->key() ?? $namespace;
    }

    /**
     * @param  Collection<int, IconReference>  $references
     * @param  Collection<int, IconReference>  $unresolved
     * @return array{references: int, unresolved: list<array<string, mixed>>, build: list<array{vendor: string, icons: list<string>, command: string}>}
     */
    protected function toJson(Collection $references, Collection $unresolved, VendorRegistry $registry): array
    {
        return [
            'references' => $references->count(),
            'unresolved' => array_values($unresolved->map(fn (IconReference $reference) => [
                'file' => $reference->package === null ? $reference->relativeFile() : null,
                'line' => $reference->package === null ? $reference->line : null,
                'package' => $reference->package,
                'name' => $reference->name(),
                'vendor' => $this->vendorKey($registry, $reference->namespace),
                'variant' => $reference->variant,
                'reason' => $reference->reason(),
            ])->all()),
            'build' => $this->buildCommands($unresolved, $registry),
        ];
    }

    /**
     * The icons installed packages declare they need, as references. With every vendor checked,
     * a namespace no configured vendor publishes to is reported, since the icon would otherwise
     * only fail when it renders; checking one vendor leaves the other namespaces alone.
     *
     * @param  list<string>  $namespaces  The namespaces of the vendors being checked.
     * @return Collection<int, IconReference>
     */
    protected function declared(VendorRegistry $registry, array $namespaces, bool $oneVendor, string $iconsPath): Collection
    {
        $installed = $this->laravel->make(DeclaredIcons::class);
        $references = collect();

        foreach ($installed->all() as $package => $declared) {
            foreach ($declared as $namespace => $icons) {
                $configured = $registry->findByNamespace($namespace) !== null;

                if ($configured ? ! in_array($namespace, $namespaces, true) : $oneVendor) {
                    continue;
                }

                foreach ($icons as $icon) {
                    $references->push(new IconReference(
                        file: $package,
                        line: 0,
                        namespace: $namespace,
                        icon: $icon,
                        iconsPath: $iconsPath,
                        package: $package,
                        vendorConfigured: $configured,
                    ));
                }
            }
        }

        return $references;
    }

    /**
     * Report a problem that stops the check, as JSON when that was asked for.
     */
    protected function failWith(string $message, ?string $detail = null): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['error' => trim($message.' '.$detail)], JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $this->components->error($message);

        if ($detail !== null) {
            $this->line("  {$detail}");
        }

        return self::FAILURE;
    }

    /**
     * Where to look.
     *
     * Defaults to every registered view path rather than just resources/views, so templates
     * that live in a package or a module are covered too, and to the application's app and
     * config directories, where enums, components and menus keep icon names in PHP.
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
            return [resource_path('views'), app_path(), config_path()];
        }

        $paths = [...$finder->getPaths(), app_path(), config_path()];

        foreach ($finder->getHints() as $namespacePaths) {
            $paths = array_merge($paths, $namespacePaths);
        }

        return array_values(array_unique(array_map('strval', $paths)));
    }
}
