<?php

namespace Ympact\FluxIcons\Build;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Writes the Blade component of one icon from the stub and the markup of its variants.
 */
class ComponentWriter
{
    protected ?string $template = null;

    public function __construct(
        protected string $stub = __DIR__.'/../../resources/stubs/icon.blade.stub',
    ) {}

    /**
     * @param  array<string, string>  $variants  The <svg> markup of each Flux variant.
     * @param  bool  $blaze  Whether the installed Flux compiles components with Blaze.
     * @return string The path of the component written.
     */
    public function write(string $directory, string $namespace, string $name, array $variants, string $credits, string $fluxCredits, bool $blaze): string
    {
        $component = Str::of($this->template ??= File::get($this->stub));

        foreach ($variants as $variant => $svg) {
            // only the outer <svg> takes the component's attributes; a raw icon nests the source's own
            $svg = Str::replaceFirst('<svg', '<svg {{ $attributes->class($classes) }}', $svg);
            $component = $component->replace('{'.Str::upper($variant).'}', $svg);
        }

        $component = $component
            ->replace('{INFO_ICON_NAME}', $name)
            ->replace('{INFO_ICON_USAGE}', "<flux:icon.{$namespace}.{$name} /> or <flux:icon name=\"{$namespace}.{$name}\" />")
            ->replace('{INFO_CREDITS}', $credits)
            ->replace('{INFO_FLUX_VERSION}', $fluxCredits)
            ->replace('{INFO_BUILD_DATE}', now()->format('Y-m-d H:i:s'))
            ->replace('{BLAZE}', $blaze ? '@blaze' : '');

        File::ensureDirectoryExists($directory);
        File::put($path = "{$directory}/{$name}.blade.php", (string) $component);

        return $path;
    }
}
