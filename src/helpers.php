<?php

declare(strict_types=1);

if (! function_exists('svg')) {
    /**
     * helper function to use the icon in blade
     * for easy transitining from Blade Icons to Flux Icons package
     */
    function svg(string $name, $class = '', array $attributes = []): Svg
    {
        return app('flux-icons')->svg($name, $class, $attributes);
    }

}
