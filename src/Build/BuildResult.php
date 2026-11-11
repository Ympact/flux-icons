<?php

namespace Ympact\FluxIcons\Build;

/**
 * What one build of a vendor wrote, and what it could not.
 */
final class BuildResult
{
    /**
     * @param  list<string>  $built  The names of the components written.
     * @param  array<string, string>  $skipped  Icons that were not written, with the reason.
     * @param  list<string>  $missing  Requested icons the vendor does not ship.
     */
    public function __construct(
        public readonly array $built = [],
        public readonly array $skipped = [],
        public readonly array $missing = [],
    ) {}
}
