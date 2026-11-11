<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Services\Vendors\Tabler as V1Tabler;
use Ympact\FluxIcons\Types\Icon;
use Ympact\FluxIcons\Types\SvgPath;
use Ympact\FluxIcons\Vendors\Tabler;

/*
 * The Tabler class has to adjust icons exactly as the v1 callbacks it replaces, so moving the
 * build onto it changes no output. Goes when #17 removes the v1 callbacks.
 */
it('adjusts icons the way the v1 tabler callbacks do', function (string $name, string $variant, string $drawing) {
    $file = sys_get_temp_dir().'/flux-icons-tabler-'.Str::random(8).'.svg';
    File::put($file, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'.$drawing.'</svg>');

    try {
        $v1 = Icon::fromFile($file, $variant, 'outline', $name)->process();
        $v2 = Icon::fromFile($file, $variant, 'outline', $name)->process();

        $describe = fn ($paths) => collect($paths)->map(fn (SvgPath $path) => $path->getAttributes())->map(fn ($attributes) => array_map('strval', array_map(fn ($node) => $node->nodeValue, $attributes)))->values()->all();
        $v1->setPaths(V1Tabler::transform($v1->getPaths(), $v1));
        $v2->setPaths((new Tabler)->transform($v2->getPaths(), $v2));

        expect($describe($v2->getPaths()))->toBe($describe($v1->getPaths()))
            ->and((new Tabler)->strokeWidth($v2, 1.5))->toBe((float) V1Tabler::strokeWidth($v1));
    } finally {
        File::delete($file);
    }
})->with(['confetti', 'refresh', 'arrow-left', 'dots', 'home'])
    ->with(['outline', 'solid', 'mini', 'micro'])
    ->with([
        'with a bounding box' => '<path d="M0 0h24v24H0z" fill="none"/><path d="M1 1h2"/><path d="M2 2h2"/><path d="M3 3h2"/><path d="M4 4h2"/>',
        'with a small circle' => '<path d="M5 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M1 1h2"/>',
        'plain' => '<path d="M1 1h2"/><path d="M2 2h2"/>',
    ]);
