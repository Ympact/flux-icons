<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Ympact\FluxIcons\Services\IconBuilder;

/*
 * Alpha set up like Tabler: a stroked outline with round caps, and a solid variant that
 * falls back to the outline. `dots` has no filled file, `home` has one.
 */
beforeEach(function () {
    $this->project = enterTemporaryProject();

    Config::set('flux-icons', fixtureConfig());
    Config::set('flux-icons.request_npm_dev', false);
    Config::set('flux-icons.vendors.alpha.variants.outline.attributes', ['stroke-linecap' => 'round']);
    Config::set('flux-icons.vendors.alpha.variants.solid.attributes', ['fill-rule' => 'evenodd']);
    Config::set('flux-icons.vendors.alpha.variants.solid.fallback', 'default');
});

afterEach(fn () => leaveTemporaryProject($this->project));

/**
 * @return array<int,string> the opening svg tags of a built component, one per variant
 */
function svgTags(string $icon): array
{
    preg_match_all('/<svg .*?">/', File::get(resource_path("views/flux/icon/alpha/{$icon}.blade.php")), $matches);

    return $matches[0];
}

it('gives every variant drawn from the outline the outline attributes', function () {
    (new IconBuilder('alpha', ['dots']))->buildIcons();

    $tags = svgTags('dots');

    expect($tags)->toHaveCount(4)->each->toContain('stroke-linecap="round"');
    expect($tags)->each->not->toContain('fill-rule="evenodd"');
});

it('keeps the solid attributes on variants drawn from a solid file', function () {
    (new IconBuilder('alpha', ['home']))->buildIcons();

    $tags = svgTags('home');

    expect(collect($tags)->filter(fn ($tag) => str_contains($tag, 'stroke-linecap="round"')))->toHaveCount(1)
        ->and(collect($tags)->filter(fn ($tag) => str_contains($tag, 'fill-rule="evenodd"')))->toHaveCount(3);
});
