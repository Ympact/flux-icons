<?php

namespace Ympact\FluxIcons\Build;

use InvalidArgumentException;
use Ympact\FluxIcons\Variants\Variant;

/**
 * Finds the source files of a vendor's variants inside its installed package.
 */
class SourceResolver
{
    /**
     * @param  string  $packagePath  The package root joined with the vendor's base path.
     */
    public function __construct(protected string $packagePath) {}

    /**
     * Every icon the variant ships, as icon name to file, in the order the files are found.
     *
     * @return array<string, string>
     */
    public function icons(Variant $variant): array
    {
        $source = $variant->getSource()
            ?? throw new InvalidArgumentException("Variant [{$variant->getName()}] has no source to list icons from.");

        $icons = [];

        foreach (glob($source->path($this->packagePath).'/'.$source->pattern()) ?: [] as $file) {
            if ($source->accepts($file)) {
                $icons[$source->iconName($file)] ??= $file;
            }
        }

        return $icons;
    }

    /**
     * The file holding the icon in this variant, or null when the variant does not ship it.
     */
    public function file(Variant $variant, string $name): ?string
    {
        $source = $variant->getSource();

        if ($source === null) {
            return null;
        }

        $file = (glob($source->path($this->packagePath).'/'.$source->fileName($name).'.svg') ?: [])[0] ?? null;

        return $file !== null && $source->accepts($file) ? $file : null;
    }
}
