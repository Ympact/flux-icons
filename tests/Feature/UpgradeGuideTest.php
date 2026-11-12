<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Build\Builder;
use Ympact\FluxIcons\Events\IconsBuilt;

/*
 * UPGRADE.md ports a custom vendor from version 1. Building its class from the guide itself keeps
 * the example in step with what the version 1 config it replaces drew.
 */
it('builds the vendor the upgrade guide ports, as its version 1 config drew it', function () {
    $guide = (string) file_get_contents(dirname(__DIR__, 2).'/UPGRADE.md');

    expect(preg_match('/```php\n(namespace App\\\\FluxIcons\\\\Vendors;.*?)```/s', $guide, $example))
        ->toBe(1, 'UPGRADE.md lost the example vendor class this test builds.');

    $class = 'GuideVendor'.Str::random(8);
    eval(str_replace(['namespace App\FluxIcons\Vendors;', 'class HouseStyle '], ['', "class {$class} "], $example[1]));

    Event::fake([IconsBuilt::class]);
    $root = sys_get_temp_dir().'/flux-icons-guide-'.Str::random(8);
    $line = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-linecap="square"><path d="M1 1h2"/></svg>';
    File::ensureDirectoryExists("{$root}/node_modules/@acme/icons/svg/line");
    File::ensureDirectoryExists("{$root}/node_modules/@acme/icons/svg/fill");
    File::put("{$root}/node_modules/@acme/icons/svg/line/house-door.svg", $line);
    File::put("{$root}/node_modules/@acme/icons/svg/fill/house-door-fill.svg", $line);
    File::put("{$root}/node_modules/@acme/icons/svg/line/bell.svg", $line);

    try {
        $result = (new Builder("{$root}/node_modules", "{$root}/out"))->build(new $class);
        $door = (string) file_get_contents("{$root}/out/house/door.blade.php");
    } finally {
        File::deleteDirectory($root);
    }

    // namespace() and iconName() of the guide, a stroke of 2 with the vendor's own square caps,
    // and a bell without a filled drawing that falls back to its outline, as version 1 drew it
    expect($result->built)->toBe(['bell', 'door'])
        ->and($result->skipped)->toBe([])
        ->and($door)->toContain('stroke-width="2" stroke-linecap="square"')
        ->and($door)->not->toContain('round');
});
