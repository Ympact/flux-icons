<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Str;

/**
 * One `namespace.icon` reference found in a Blade template.
 */
class IconReference
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $namespace,
        public readonly string $icon,
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
        return resource_path("views/flux/icon/{$this->namespace}/{$this->icon}.blade.php");
    }

    public function exists(): bool
    {
        return ! $this->isMalformed() && is_file($this->path());
    }

    /**
     * The file this was found in, relative to the application root when it sits under it.
     */
    public function relativeFile(): string
    {
        return Str::after($this->file, base_path().DIRECTORY_SEPARATOR);
    }
}
