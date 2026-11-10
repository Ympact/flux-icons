<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Support\VendorRegistry;

/**
 * Generates a vendor class in the application: a subclass of a registered vendor to adjust
 * it, or a new vendor to fill in.
 */
class MakeVendorCommand extends Command
{
    protected const NAMESPACE = 'App\\FluxIcons\\Vendors';

    protected $signature = 'flux-icons:make-vendor
                            {name : The class name of the vendor, such as HouseStyle}
                            {--extends= : The key of a registered vendor to extend, such as tabler}';

    protected $description = 'Generate a vendor class, optionally extending a registered vendor';

    public function handle(): int
    {
        $name = $this->argument('name');
        $name = is_string($name) ? $name : '';
        $class = Str::studly($name);

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $class) !== 1) {
            $this->error("'{$name}' is not a valid class name.");

            return self::FAILURE;
        }

        $extends = $this->option('extends');
        $parent = null;

        if (is_string($extends) && $extends !== '') {
            $registry = VendorRegistry::fromConfig();

            if (! $registry->has($extends)) {
                $this->error("Vendor configuration for '{$extends}' not found.");

                return self::FAILURE;
            }

            $parent = $registry->get($extends)::class;
        }

        $path = app_path("FluxIcons/Vendors/{$class}.php");

        if (File::exists($path)) {
            $this->error("{$path} already exists.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $parent !== null ? $this->subclass($class, $parent) : $this->vendor($class));

        $key = is_string($extends) && $extends !== '' ? $extends : Str::kebab($class);
        $this->info("Created {$path}.");
        $this->line('Register it in config/flux-icons.php to use it:');
        $this->line("  '{$key}' => \\".self::NAMESPACE."\\{$class}::class,");

        return self::SUCCESS;
    }

    /**
     * @param  class-string  $parent
     */
    protected function subclass(string $class, string $parent): string
    {
        $namespace = self::NAMESPACE;
        $base = class_basename($parent) === $class ? "{$parent} as BaseVendor" : $parent;
        $extends = class_basename($parent) === $class ? 'BaseVendor' : class_basename($parent);

        return <<<PHP
        <?php

        namespace {$namespace};

        use {$base};

        /**
         * Override variants(), transform(), attributes(), strokeWidth() or iconName() to change
         * how the icons are built. A variant can be redefined by name, so a subclass only
         * restates what it changes: parent::variants()->variant('outline', fn (\$variant) => ...)
         */
        class {$class} extends {$extends}
        {
        }

        PHP;
    }

    protected function vendor(string $class): string
    {
        $namespace = self::NAMESPACE;

        return <<<PHP
        <?php

        namespace {$namespace};

        use Ympact\\FluxIcons\\Contracts\\Vendor;
        use Ympact\\FluxIcons\\Sources\\Source;
        use Ympact\\FluxIcons\\Variants\\Fallback;
        use Ympact\\FluxIcons\\Variants\\Stroke;
        use Ympact\\FluxIcons\\Variants\\Template;
        use Ympact\\FluxIcons\\Variants\\VariantSet;

        /**
         * The npm package the icons come from, and the variants Flux asks for: outline, solid,
         * mini and micro. Sources are directories inside the package, relative to basePath().
         */
        class {$class} extends Vendor
        {
            public function package(): string
            {
                return 'icon-package';
            }

            public function basePath(): string
            {
                return 'icons';
            }

            public function variants(): VariantSet
            {
                return VariantSet::make()
                    ->variant('outline', fn (\$variant) => \$variant
                        ->template(Template::Outline)
                        ->source(Source::dir('outline'))
                        ->stroke(new Stroke(1.5))
                        ->default()
                    )
                    ->variant('solid', fn (\$variant) => \$variant
                        ->template(Template::Solid)
                        ->source(Source::dir('solid'))
                        ->fallback(Fallback::defaultVariant())
                    )
                    ->variant('mini', fn (\$variant) => \$variant->basedOn('solid'))
                    ->variant('micro', fn (\$variant) => \$variant->basedOn('solid'));
            }
        }

        PHP;
    }
}
