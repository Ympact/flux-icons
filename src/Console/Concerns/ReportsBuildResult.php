<?php

namespace Ympact\FluxIcons\Console\Concerns;

use Ympact\FluxIcons\Build\BuildResult;

/**
 * Tells the user which requested icons the vendor does not ship, and which it could not build.
 */
trait ReportsBuildResult
{
    protected function report(BuildResult $result, bool $verbose): void
    {
        if ($result->missing !== []) {
            $this->warn('Icons not found: '.implode(', ', $result->missing));
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
