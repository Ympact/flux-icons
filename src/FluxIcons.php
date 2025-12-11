<?php

namespace Ympact\FluxIcons;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ympact\FluxIcons\FluxIconsManager
 */
class FluxIcons extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'flux-icons';
    }
}
