<?php

namespace Ympact\FluxIcons\Console\Concerns;

use Ympact\FluxIcons\Build\BuildResult;

/**
 * Tells the user which requested icons the vendor does not ship, which it could not build, and
 * what else went wrong without stopping the build.
 */
trait ReportsBuildResult
{
    protected function report(BuildResult $result, bool $verbose): void
    {
        if ($result->missing !== []) {
            $this->warn('Icons not found: '.implode(', ', $result->missing));
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        if ($result->skipped === []) {
            return;
        }

        $this->warn(count($result->skipped).' icons were skipped'.($verbose ? ':' : '; run with -v to see why.'));

        if ($verbose) {
            foreach ($result->skipped as $name => $reason) {
                $this->line("  {$name}: {$reason}");
            }
        }
    }
}
