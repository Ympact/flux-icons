<?php

namespace Ympact\FluxIcons\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ympact\FluxIcons\Support\VendorRegistry;

/**
 * Generates a vendor class in the application: a subclass of a registered vendor to adjust
 * it, or a new vendor to fill in.
 */
class MakeVendorCommand extends GeneratorCommand
{
    /** The classes a new vendor's stub imports, which the vendor's own name must not shadow. */
    protected const STUB_IMPORTS = ['Vendor', 'Source', 'Fallback', 'Stroke', 'Template', 'VariantSet'];

    protected $signature = 'flux-icons:make-vendor
                            {name : The class name of the vendor, such as HouseStyle}
                            {--extends= : The key of a registered vendor to extend, such as tabler}
                            {--force : Overwrite the vendor class if it exists}';

    protected $description = 'Generate a vendor class, optionally extending a registered vendor';

    protected $type = 'Vendor class';

    /** @var class-string|null The vendor class the generated one extends. */
    protected ?string $parent = null;

    /**
     * @return bool|null False when nothing was generated; like Laravel's own make commands, that
     *                   exits with 0 after printing the error.
     */
    public function handle(): ?bool
    {
        $class = $this->getNameInput();

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $class) !== 1) {
            $this->components->error("'".parent::getNameInput()."' is not a valid class name.");

            return false;
        }

        $extends = $this->option('extends');
        $extends = is_string($extends) && $extends !== '' ? $extends : null;

        if ($extends === null && in_array($class, self::STUB_IMPORTS, true)) {
            $this->components->error("A vendor named {$class} would clash with the {$class} class it imports. Choose another name.");

            return false;
        }

        if ($extends !== null) {
            try {
                $registry = VendorRegistry::fromConfig();
            } catch (InvalidArgumentException $exception) {
                $this->components->error($exception->getMessage());

                return false;
            }

            if (! $registry->has($extends)) {
                $this->components->error("Vendor configuration for '{$extends}' not found.");

                return false;
            }

            $this->parent = $registry->get($extends)::class;

            // with --force this would replace the registered class with one that extends itself
            if ($this->parent === $this->qualifyClass($class)) {
                $this->components->error("{$class} cannot extend itself: '{$extends}' is registered as {$this->parent}. Choose another name.");

                return false;
            }
        }

        if (parent::handle() === false) {
            return false;
        }

        $this->line('Register it in config/flux-icons.php to use it:');
        $this->line("  '".($extends ?? Str::kebab($class))."' => \\".$this->qualifyClass($class).'::class,');

        if (! is_file(config_path('flux-icons.php'))) {
            $this->line('Publish that config first: php artisan vendor:publish --tag=flux-icons-config');
        }

        return null;
    }

    protected function getNameInput(): string
    {
        return Str::studly(parent::getNameInput());
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\FluxIcons\\Vendors';
    }

    protected function getStub(): string
    {
        return __DIR__.'/../../resources/stubs/'.($this->parent === null ? 'vendor.stub' : 'vendor-subclass.stub');
    }

    /**
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        if ($this->parent === null) {
            return $stub;
        }

        // a subclass named after the vendor it extends imports that vendor under an alias
        $aliased = class_basename($this->parent) === class_basename($name);

        return str_replace(
            ['{{ parentImport }}', '{{ parentClass }}'],
            [$aliased ? "{$this->parent} as BaseVendor" : $this->parent, $aliased ? 'BaseVendor' : class_basename($this->parent)],
            $stub
        );
    }
}
