<?php

namespace Ympact\FluxIcons\Build;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Variants\FluxVariant;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Writes the Blade component of one icon from the stub and the markup of its variants.
 *
 * The component has one case per variant built, takes the vendor's default variant when
 * none is asked for, and draws that default for a variant it was not built with.
 */
class ComponentWriter
{
    protected ?string $template = null;

    public function __construct(
        protected string $stub = __DIR__.'/../../resources/stubs/icon.blade.stub',
    ) {}

    /**
     * @param  VariantSet  $variants  The resolved variants, for their size classes and default.
     * @param  array<string, string>  $markup  The <svg> markup of each variant built, in the order to write them.
     * @param  bool  $blaze  Whether the installed Flux compiles components with Blaze.
     * @return string The path of the component written.
     */
    public function write(string $directory, string $namespace, string $name, VariantSet $variants, array $markup, string $credits, string $fluxCredits, bool $blaze): string
    {
        $default = $variants->defaultName();
        $defaultClasses = $variants->default()->getClasses() ?? FluxVariant::Outline->classes();
        $literal = fn (string $value) => var_export($value, true);

        $cases = [];
        $classes = [];

        foreach (array_keys($markup) as $index => $variant) {
            // only the outer <svg> takes the component's attributes; a raw icon nests the source's own
            $svg = Str::replaceFirst('<svg', '<svg {{ $attributes->class($classes) }}', $markup[$variant]);
            $case = $index === 0 ? '<?php switch ($variant): case ('.$literal($variant).'): ?>' : '    <?php case ('.$literal($variant).'): ?>';

            $cases[] = "{$case}\n{$svg}\n        <?php break; ?>\n";
            $classes[] = '        '.$literal($variant).' => '.$literal($variants->get($variant)?->getClasses() ?? $defaultClasses).',';
        }

        // one pass, so markup that happens to contain a placeholder is left alone
        $component = strtr($this->template ??= File::get($this->stub), [
            '{INFO_ICON_NAME}' => $name,
            '{INFO_ICON_USAGE}' => "<flux:icon.{$namespace}.{$name} /> or <flux:icon name=\"{$namespace}.{$name}\" />",
            '{INFO_ICON_VARIANTS}' => implode(', ', array_keys($markup)),
            '{INFO_CREDITS}' => $credits,
            '{INFO_FLUX_VERSION}' => $fluxCredits,
            '{INFO_BUILD_DATE}' => now()->format('Y-m-d H:i:s'),
            '{BLAZE}' => $blaze ? '@blaze' : '',
            '{DEFAULT_VARIANT}' => $literal($default),
            '{VARIANT_NAMES}' => implode(', ', array_map($literal, array_keys($markup))),
            '{VARIANT_CLASSES}' => implode("\n", $classes),
            '{VARIANT_CASES}' => implode("\n", $cases)."\n<?php endswitch; ?>",
        ]);

        File::ensureDirectoryExists($directory);
        File::put($path = "{$directory}/{$name}.blade.php", $component);

        return $path;
    }
}
