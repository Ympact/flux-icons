<?php

namespace Ympact\FluxIcons;

if (! function_exists('Ympact\FluxIcons\arrayMergeRecursive')) {
    /**
     * Summary of Ympact\FluxIcons\arrayMergeRecursive
     *
     * @param  array  $arrays
     */
    function arrayMergeRecursive(...$arrays): array
    {
        $result = array_replace_recursive(...$arrays);

        // Remove null values
        $result = array_filter($result, function ($value) {
            return ! is_null($value);
        });

        return $result;
    }
}
