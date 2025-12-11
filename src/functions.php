<?php

declare(strict_types=1);

namespace Ympact\FluxIcons;

use Ympact\FluxIcons\FluxIconsManager;

if (! function_exists(__NAMESPACE__ . '\\arrayMergeRecursive')) {
    /**
     * Delegate to FluxIconsManager::arrayMergeRecursive
     *
     * @param mixed ...$arrays
     * @return array
     */
    function arrayMergeRecursive(...$arrays): array
    {
        $manager = new FluxIconsManager();

        return $manager->arrayMergeRecursive(...$arrays);
    }
}
