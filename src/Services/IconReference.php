<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Str;

/**
 * One `namespace.icon` reference found in a Blade template or a PHP source.
 */
class IconReference
{
    /** Why a reference does not resolve, as `reason()` gives it. */
    public const MALFORMED = 'malformed';

    public const NOT_BUILT = 'not-built';

    public const UNKNOWN_VARIANT = 'unknown-variant';

    /**
     * @param  string|null  $variant  The variant a `<flux:icon>` tag asks for, when it is written as a literal.
     * @param  string|null  $iconsPath  Where the icons are built; resources/views/flux/icon when null.
     */
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $namespace,
        public readonly string $icon,
        public readonly ?string $variant = null,
        public readonly ?string $iconsPath = null,
    ) {}

    /**
     * The reference as it is written in the template.
     */
    public function name(): string
    {
        return "{$this->namespace}.{$this->icon}";
    }

    /**
     * Whether the name could ever resolve.
     *
     * A dot left in the icon means something like `tabler.tabler.check`: the reference is
     * malformed rather than merely pointing at an icon that has not been built.
     */
    public function isMalformed(): bool
    {
        return Str::contains($this->icon, '.');
    }

    /**
     * Where the built icon for this reference would live.
     */
    public function path(): string
    {
        $directory = $this->iconsPath ?? resource_path('views/flux/icon');

        return "{$directory}/{$this->namespace}/{$this->icon}.blade.php";
    }

    public function exists(): bool
    {
        return ! $this->isMalformed() && is_file($this->path());
    }

    /**
     * Why the reference does not resolve, or null when it does.
     *
     * A variant the icon was not built with renders the default variant rather than failing,
     * but it is still not what the template asked for.
     *
     * @return self::MALFORMED|self::NOT_BUILT|self::UNKNOWN_VARIANT|null
     */
    public function reason(): ?string
    {
        return match (true) {
            $this->isMalformed() => self::MALFORMED,
            ! $this->exists() => self::NOT_BUILT,
            $this->variant !== null && ! $this->builtWithVariant($this->variant) => self::UNKNOWN_VARIANT,
            default => null,
        };
    }

    /**
     * Whether the built icon has the variant. An icon built before its header listed the
     * variants cannot tell, and is given the benefit of the doubt.
     */
    protected function builtWithVariant(string $variant): bool
    {
        if (! preg_match('/^\s*Variants:\s*(.+?)\s*$/m', (string) file_get_contents($this->path()), $listed)) {
            return true;
        }

        return in_array($variant, array_map('trim', explode(',', $listed[1])), true);
    }

    /**
     * The file this was found in, relative to the application root when it sits under it.
     */
    public function relativeFile(): string
    {
        return Str::after($this->file, base_path().DIRECTORY_SEPARATOR);
    }
}
