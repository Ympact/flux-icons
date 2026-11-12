<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Build\Credits;

beforeEach(function () {
    $this->package = sys_get_temp_dir().'/flux-icons-credits-'.Str::random(8);
    File::ensureDirectoryExists($this->package);
});

afterEach(fn () => File::deleteDirectory($this->package));

it('credits the package, its author and the licence of its icons', function () {
    File::put("{$this->package}/package.json", '{"name": "@fortawesome/fontawesome-free", "version": "7.3.1", "author": "The Font Awesome Team"}');

    expect((new Credits)->package($this->package, 'CC-BY-4.0'))
        ->toBe('@fortawesome/fontawesome-free (7.3.1) by The Font Awesome Team, licensed under CC-BY-4.0');
});

it('credits a package without an author or a known licence', function () {
    File::put("{$this->package}/package.json", '{"name": "alpha", "version": "1.0.0", "author": {"name": "Alpha Team"}}');

    expect((new Credits)->package($this->package))->toBe('alpha (1.0.0) by Alpha Team');

    File::put("{$this->package}/package.json", '{"name": "beta", "version": "2.0.0"}');

    expect((new Credits)->package($this->package, 'MIT'))->toBe('beta (2.0.0), licensed under MIT');
});

it('still names the licence when the package details are missing', function () {
    expect((new Credits)->package($this->package, 'CC-BY-4.0'))
        ->toBe('-- Package details not found --, licensed under CC-BY-4.0');
});
