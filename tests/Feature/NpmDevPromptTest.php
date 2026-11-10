<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Laravel\Prompts\Prompt;
use Ympact\FluxIcons\Services\IconBuilder;

/*
 * A build of more than 100 icons with request_npm_dev on, run without a terminal: every
 * prompt then answers with its default.
 */
const ICONS_ABOVE_PROMPT_THRESHOLD = 101;
const SECONDS_WITHOUT_PAUSE = 15;

beforeEach(function () {
    $this->project = enterTemporaryProject();

    Config::set('flux-icons', fixtureConfig());
    Config::set('flux-icons.request_npm_dev', true);

    foreach (range(1, ICONS_ABOVE_PROMPT_THRESHOLD) as $number) {
        foreach (['outline', 'filled'] as $directory) {
            File::copy(
                "{$this->project}/tests/vendor/alpha/icons/{$directory}/home.svg",
                "{$this->project}/tests/vendor/alpha/icons/{$directory}/copy-{$number}.svg"
            );
        }
    }

    Prompt::flushState();
    Prompt::interactive(false);
});

afterEach(fn () => leaveTemporaryProject($this->project));

it('does not pause between icons or start npm when there is no terminal', function () {
    $start = microtime(true);

    (new IconBuilder('alpha'))->buildIcons();

    // the pause is a third of a second per icon, so about 34 seconds here
    expect(microtime(true) - $start)->toBeLessThan(SECONDS_WITHOUT_PAUSE)
        ->and(count(builtIcons('alpha')))->toBeGreaterThan(ICONS_ABOVE_PROMPT_THRESHOLD)
        ->and(npmCalls($this->project))->toBe([]);
});
