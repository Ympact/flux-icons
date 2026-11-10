<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Ympact\FluxIcons\Events\IconsBuilt;
use Ympact\FluxIcons\Services\IconBuilder;

beforeEach(function () {
    Config::set('flux-icons', fixtureConfig());
    Config::set('flux-icons.request_npm_dev', false);

    $this->outputDir = resource_path('views/flux/icon/alpha');
    Event::fake([IconsBuilt::class]);
});

afterEach(fn () => File::deleteDirectory($this->outputDir));

it('announces the icons a build wrote', function () {
    (new IconBuilder('alpha', ['home']))->buildIcons();

    Event::assertDispatched(IconsBuilt::class, function (IconsBuilt $event) {
        return $event->vendor === 'alpha'
            && $event->namespace === 'alpha'
            && $event->directory === $this->outputDir
            && $event->icons === ['home'];
    });

    expect("{$this->outputDir}/home.blade.php")->toBeFile();
});

it('announces nothing when a build wrote no icons', function () {
    (new IconBuilder('alpha', ['does-not-exist']))->buildIcons();

    Event::assertNotDispatched(IconsBuilt::class);
});
