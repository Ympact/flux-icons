<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Collection;

class PackageManager
{

    public function fluxVersion(){
        // get livewire/flux version from composer
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        return $composer['require']['livewire/flux'] ?? null;
    }
