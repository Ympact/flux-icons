<?php

namespace Ympact\FluxIcons\Build;

use Illuminate\Support\Arr;

/**
 * The credit lines written into the header of a built component.
 */
class Credits
{
    /**
     * Name, version and author of the npm package the icons come from.
     */
    public function package(string $packageRoot): string
    {
        $file = $packageRoot.'/package.json';

        if (! is_file($file)) {
            return '-- Package details not found --';
        }

        $details = (array) json_decode((string) file_get_contents($file), true);
        $name = Arr::get($details, 'name');
        $author = Arr::get($details, 'author');
        $name = is_array($name) ? ($name['name'] ?? '') : $name;
        $author = is_array($author) ? ($author['name'] ?? null) : $author;

        return $name.' ('.Arr::get($details, 'version').') '.($author ? 'by '.$author : '');
    }

    /**
     * Name, version and author of the installed livewire/flux, from the application's lock file.
     */
    public function flux(): string
    {
        $lock = base_path('composer.lock');

        if (! is_file($lock)) {
            return '';
        }

        $packages = (array) (json_decode((string) file_get_contents($lock), true)['packages'] ?? []);
        $flux = collect($packages)->firstWhere('name', 'livewire/flux');

        return is_array($flux)
            ? $flux['name'].' ('.$flux['version'].') by '.Arr::get($flux, 'authors.0.name')
            : '';
    }
}
