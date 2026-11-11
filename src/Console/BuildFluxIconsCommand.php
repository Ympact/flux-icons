<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Ympact\FluxIcons\Build\Builder;
use Ympact\FluxIcons\Build\BuildResult;
use Ympact\FluxIcons\Console\Concerns\ReportsBuildResult;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Services\DeclaredIcons;
use Ympact\FluxIcons\Services\NpmManager;
use Ympact\FluxIcons\Support\VendorRegistry;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multisearch;
use function Laravel\Prompts\select;

/**
 * Builds the Flux icon components of one vendor, installing its npm package first if needed.
 */
class BuildFluxIconsCommand extends Command
{
    use ReportsBuildResult;

    /** Builds this large compete with a running `npm run dev` for file watchers. */
    protected const LARGE_BUILD = 100;

    /** Pause between icons while `npm run dev` runs, in microseconds: a third of a second. */
    protected const NPM_DEV_PAUSE = 333333;

    protected $signature = 'flux-icons:build
                            {vendor? : The vendor icon package to use}
                            {--I|icons= : The icons to build (single or comma separated list)}
                            {--A|all : All icons from the vendor}
                            {--M|merge : Merge the icons from the --icons option with the default icons}
                            {--O|options=* : Options of the vendor for this build, as name=value (single or comma separated list)}';

    protected $description = 'Build icons for Flux using a specific icon package';

    public function handle(): int
    {
        $noInteraction = (bool) $this->option('no-interaction');
        $verbose = (bool) $this->option('verbose');

        try {
            $registry = VendorRegistry::fromConfig();
            $options = $this->requestedOptions();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $key = $this->vendorKey($registry, $noInteraction);

        if ($key === null) {
            $this->error('Name the vendor to build from, or set defaults.vendor in config/flux-icons.php.');

            return self::FAILURE;
        }

        if (! $registry->has($key)) {
            $this->error("Vendor configuration for '{$key}' not found.");

            return self::FAILURE;
        }

        $all = (bool) $this->option('all');
        $merge = (bool) $this->option('merge');

        if ($this->option('icons') && $all) {
            $this->error("You can't use the --icons option in combination with the --all option");

            return self::FAILURE;
        }

        if ($merge && $all) {
            $this->error("You can't use the --merge option in combination with the --all option");

            return self::FAILURE;
        }

        try {
            $vendor = $registry->get($key)->withOptions($options);

            if (! $noInteraction) {
                $vendor = $vendor->withOptions($this->promptedOptions($vendor, $options));
            }
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Checking if vendor: {$key} is installed");

        try {
            $this->laravel->make(NpmManager::class)->require($vendor, $verbose);
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $builder = $this->laravel->make(Builder::class);

        // npm may install the package where the builder does not look, such as a workspace root
        try {
            $icons = $this->icons($builder, $vendor, $all, $noInteraction);

            if ($icons === []) {
                $this->error('You did not select any icons. Exiting...');

                return self::FAILURE;
            }

            // building every icon already includes the configured ones
            if ($merge && $icons !== null) {
                $icons = $this->mergeWithConfiguredIcons($icons, $this->configuredIcons($vendor));
            }

            $this->info("Building icons for vendor: {$key}".$this->describeOptions($vendor));
            $result = $this->build($builder, $vendor, $icons);
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($result, $verbose);

        if ($result->built === []) {
            $this->error("No icons were built for vendor: {$key}");

            return self::FAILURE;
        }

        $this->newLine()->info("Icons built successfully for vendor: {$key}");

        return self::SUCCESS;
    }

    /**
     * The vendor asked for, else the configured default, else the one the user picks; null when
     * there is none and no one to ask.
     */
    protected function vendorKey(VendorRegistry $registry, bool $noInteraction): ?string
    {
        $key = $this->argument('vendor') ?? config('flux-icons.defaults.vendor');

        if ($key === null && ! $noInteraction) {
            $key = select(
                label: 'From which vendor do you want to build icons?',
                options: collect($registry->keys())->sort()->values()->all(),
                scroll: 5
            );
        }

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * The options given with --options, by name.
     *
     * @return array<string, string>
     *
     * @throws InvalidArgumentException When one is not written as name=value.
     */
    protected function requestedOptions(): array
    {
        $options = [];

        foreach ((array) $this->option('options') as $given) {
            foreach (explode(',', (string) $given) as $pair) {
                if (trim($pair) === '') {
                    continue;
                }

                if (! str_contains($pair, '=')) {
                    throw new InvalidArgumentException("Give each option as name=value: [{$pair}] has no value.");
                }

                [$name, $value] = array_map('trim', explode('=', $pair, 2));
                $options[$name] = $value;
            }
        }

        return $options;
    }

    /**
     * Ask for each option of the vendor not given with --options, offering the value it builds with.
     *
     * @param  array<string, string>  $given
     * @return array<string, string>
     */
    protected function promptedOptions(Vendor $vendor, array $given): array
    {
        $chosen = [];

        foreach (array_diff_key($vendor->options(), $given) as $name => $values) {
            $chosen[$name] = (string) select(
                label: "Which {$name} do you want to build {$vendor->key()} with?",
                options: array_map('strval', $values),
                default: (string) $vendor->option($name),
            );
        }

        return $chosen;
    }

    protected function describeOptions(Vendor $vendor): string
    {
        $options = $vendor->optionValues();

        return $options === []
            ? ''
            : ' ('.implode(', ', array_map(fn ($name, $value) => "{$name}={$value}", array_keys($options), $options)).')';
    }

    /**
     * The icons to build: those asked for on the command line, else the configured ones, else
     * whatever the user picks. Null builds every icon the vendor ships.
     *
     * @return array<int, string>|null
     */
    protected function icons(Builder $builder, Vendor $vendor, bool $all, bool $noInteraction): ?array
    {
        if ($all) {
            return null;
        }

        $requested = $this->normalizeIcons($this->option('icons'));
        $configured = $this->configuredIcons($vendor);

        if ($requested !== [] || $noInteraction) {
            return $requested ?: ($configured ?: null);
        }

        $available = array_keys($builder->available($vendor));

        $options = array_filter([
            'config' => $configured !== [] ? 'The '.count($configured)." icons configured for {$vendor->key()} or declared by packages" : null,
            'select' => 'Let me choose',
            'all' => 'All '.count($available).' icons',
        ]);

        $choice = select(
            label: 'Which icons do you want to build from the vendor?',
            options: $options,
            default: $configured !== [] ? 'config' : 'select'
        );

        return match ($choice) {
            'config' => $configured,
            'all' => null,
            default => array_values(array_map('strval', multisearch(
                label: 'Which icons do you want to build from the vendor?',
                options: fn (string $value) => array_values(array_filter(
                    $available,
                    fn (string $name) => Str::contains($name, $value, ignoreCase: true)
                )),
                scroll: 10
            ))),
        };
    }

    /**
     * The icons the config lists for the vendor, followed by those installed packages declare
     * they need from its namespace.
     *
     * @return array<int, string>
     */
    protected function configuredIcons(Vendor $vendor): array
    {
        return $this->mergeWithConfiguredIcons(
            config("flux-icons.icons.{$vendor->key()}"),
            $this->laravel->make(DeclaredIcons::class)->forNamespace($vendor->namespace())
        );
    }

    /**
     * @param  array<int, string>|null  $icons
     */
    protected function build(Builder $builder, Vendor $vendor, ?array $icons): BuildResult
    {
        $total = $icons === null ? count($builder->available($vendor)) : count($icons);
        $npmDevRunning = config('flux-icons.request_npm_dev') && $total > self::LARGE_BUILD && $this->confirmNpmDevRunning($total);

        $bar = null;

        $result = $builder->build($vendor, $icons, function (string $name, int $done, int $selected) use (&$bar, $npmDevRunning) {
            $bar ??= tap(new ProgressBar($this->output, $selected))->start();
            $bar->advance();

            if ($npmDevRunning) {
                usleep(self::NPM_DEV_PAUSE);
            }
        });

        $bar?->finish();
        $this->newLine();

        if ($npmDevRunning) {
            info('In case `npm run dev` stopped during the build, start it again.');
        }

        return $result;
    }

    protected function confirmNpmDevRunning(int $total): bool
    {
        $seconds = (int) round($total * self::NPM_DEV_PAUSE / 1000000);

        info("In case npm run dev is running, we'll introduce a timeout between processing icons to prevent memory issues. \nThis will make this script take {$seconds} seconds. \nIt is better to stop `npm run dev` first before continuing.");

        // defaults to no, so a run without a terminal does not pause
        return confirm('Is `npm run dev` running?', default: false);
    }

    /**
     * Combine the requested icons with the icons configured for the vendor.
     *
     * @return array<int,string>
     */
    protected function mergeWithConfiguredIcons(mixed $icons, mixed $configVendorIcons): array
    {
        return collect($this->normalizeIcons($icons))
            ->merge($this->normalizeIcons($configVendorIcons))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Accept both a comma separated list and an array of icon names, as the --icons option and
     * the icons config give them; anything else holds no icons.
     *
     * @return array<int,string>
     */
    protected function normalizeIcons(mixed $icons): array
    {
        $icons = match (true) {
            is_string($icons) => explode(',', $icons),
            is_array($icons) => $icons,
            default => [],
        };

        return collect($icons)
            ->filter(fn ($icon) => is_string($icon))
            ->map(fn (string $icon) => trim($icon))
            ->filter()
            ->values()
            ->all();
    }
}
