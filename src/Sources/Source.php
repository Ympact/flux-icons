<?php

namespace Ympact\FluxIcons\Sources;

use Closure;
use Illuminate\Support\Str;

/**
 * Where the SVG files for a variant live inside the vendor's package, and how
 * the icon's base name maps onto a file name.
 *
 * Directories are relative to the vendor's package root, so a vendor states its
 * package once instead of repeating `node_modules/<package>` per variant.
 */
class Source
{
    protected string $directory;

    protected ?string $prefix = null;

    protected ?string $suffix = null;

    protected ?Closure $filter = null;

    final public function __construct(string $directory = '')
    {
        $this->directory = trim($directory, '/');
    }

    public static function dir(string $directory = ''): static
    {
        return new static($directory);
    }

    public function prefix(?string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function suffix(?string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * Decide whether a file in the directory belongs to this variant.
     *
     * Needed for vendors that keep several variants in one directory, telling
     * them apart by their file name (Bootstrap's `-fill`, MDI's `-outline`).
     *
     * @param  Closure(string $file): bool  $filter
     */
    public function filter(Closure $filter): static
    {
        $this->filter = $filter;

        return $this;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function hasFilter(): bool
    {
        return $this->filter !== null;
    }

    public function accepts(string $file): bool
    {
        return $this->filter === null || ($this->filter)($file);
    }

    /**
     * The file name (without extension) that holds the given icon.
     */
    public function fileName(string $icon): string
    {
        return $this->prefix.$icon.$this->suffix;
    }

    /**
     * The icon name behind a file name, stripping the variant's prefix/suffix.
     */
    public function iconName(string $fileName): string
    {
        $name = Str::of($fileName)->basename('.svg');

        // a prefix only counts at the start and a suffix only at the end, otherwise a
        // file that merely contains them somewhere would be mangled
        if ($this->prefix !== null && $this->prefix !== '' && $name->startsWith($this->prefix)) {
            $name = $name->after($this->prefix);
        }

        if ($this->suffix !== null && $this->suffix !== '' && $name->endsWith($this->suffix)) {
            $name = $name->beforeLast($this->suffix);
        }

        return $name->toString();
    }

    /**
     * The glob pattern matching every candidate file for this variant.
     */
    public function pattern(): string
    {
        // when a filter decides which files belong to the variant, the prefix and
        // suffix only describe the naming and must not narrow the glob
        return $this->hasFilter()
            ? '*.svg'
            : $this->fileName('*').'.svg';
    }

    /**
     * Resolve the directory against the package root.
     */
    public function path(string $packagePath): string
    {
        return Str::of($packagePath)->finish('/')->append($this->directory)->rtrim('/')->toString();
    }
}
