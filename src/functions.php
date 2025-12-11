<?php

declare(strict_types=1);

namespace Ympact\FluxIcons;

if (! function_exists(__NAMESPACE__.'\\arrayMergeRecursive')) {
    /**
     * Delegate to FluxIconsManager::arrayMergeRecursive
     *
     * @param  mixed  ...$arrays
     */
    function arrayMergeRecursive(...$arrays): array
    {
        $manager = new FluxIconsManager;

        return $manager->arrayMergeRecursive(...$arrays);
    }
}
