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
 * none is asked for, and draws that default for a variant it was not built with. What comes
 * from the icon package is written so that Blade and PHP read it as text, never as code.
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
            $svg = Str::replaceFirst('<svg', '<svg {{ $attributes->class($classes) }}', $this->inert($markup[$variant]));
            $case = $index === 0 ? '<?php switch ($variant): case ('.$literal($variant).'): ?>' : '    <?php case ('.$literal($variant).'): ?>';

            $cases[] = "{$case}\n{$svg}\n        <?php break; ?>\n";
            $classes[] = '        '.$literal($variant).' => '.$literal($variants->get($variant)?->getClasses() ?? $defaultClasses).',';
        }

        // one pass, so markup that happens to contain a placeholder is left alone
        $component = strtr($this->template ??= File::get($this->stub), [
            '{INFO_ICON_NAME}' => $this->commentText($name),
            '{INFO_ICON_USAGE}' => $this->commentText("<flux:icon.{$namespace}.{$name} /> or <flux:icon name=\"{$namespace}.{$name}\" />"),
            '{INFO_ICON_VARIANTS}' => $this->commentText(implode(', ', array_keys($markup))),
            '{INFO_CREDITS}' => $this->commentText($credits),
            '{INFO_FLUX_VERSION}' => $this->commentText($fluxCredits),
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

    /**
     * Markup from the icon package, written so that it renders as the package drew it and runs
     * nothing: a kept comment such as Font Awesome's "by @fontawesome" would otherwise call an
     * application's `fontawesome` directive, and a `{{ }}` or `<?php` would run on every render.
     */
    protected function inert(string $markup): string
    {
        // text and attribute values already escape <, so this is a processing instruction or a kept comment
        $markup = str_replace('<?', '&lt;?', $markup);
        // Blade compiles a component tag, and the PHP in its bound attributes, wherever one appears
        $markup = (string) preg_replace('/<(?=\/?(?:x[-:]|flux:|livewire:))/i', '&lt;', $markup);
        // an entity renders the same brace, and Blade strips a {{-- comment before an @ could escape it
        $markup = str_replace(['{{', '{!!'], ['{&#123;', '{&#33;!'], $markup);

        // where Blade reads a directive, @@ escapes it and renders a single @
        return (string) preg_replace('/\B@(?=\w)/', '@@', $markup);
    }

    /**
     * Text for the header, a Blade comment, which a `--}}` in a package's details would end early.
     */
    protected function commentText(string $text): string
    {
        return str_replace('--}}', '-- }}', $text);
    }
}
