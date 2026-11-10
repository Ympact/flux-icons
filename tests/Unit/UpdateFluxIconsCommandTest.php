<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Console\UpdateFluxIconsCommand;
use Ympact\FluxIcons\Support\VendorRegistry;
use Ympact\FluxIcons\Vendors\MaterialIcons;
use Ympact\FluxIcons\Vendors\Tabler;

/**
 * Asks which built directories an update would touch, which needs no npm.
 */
class UpdateCommandProbe extends UpdateFluxIconsCommand
{
    /**
     * @param  list<string>  $directories
     * @return list<string>
     */
    public function requested(array $directories, string $option): array
    {
        $registry = new VendorRegistry(['material-icons' => MaterialIcons::class, 'tabler' => Tabler::class]);

        return $this->onlyRequested($registry, $directories, $option);
    }
}

// material-icons publishes to material: the key and the namespace differ
it('updates a vendor named by its key or by the directory its icons were built into', function (string $option, array $expected) {
    expect((new UpdateCommandProbe)->requested(['flux-icons', 'material', 'tabler'], $option))->toBe($expected);
})->with([
    'by key' => ['material-icons', ['material']],
    'by directory' => ['material', ['material']],
    'several, spaced' => ['tabler, material-icons', ['material', 'tabler']],
]);

it('stops when none of the vendors named has built icons', function () {
    $root = sys_get_temp_dir().'/flux-icons-update-'.Str::random(8);
    File::ensureDirectoryExists("{$root}/resources/views/flux/icon/tabler");
    $this->app->setBasePath($root);
    $this->app[Kernel::class]->registerCommand(new UpdateFluxIconsCommand);
    Config::set('flux-icons.vendors', ['tabler' => Tabler::class]);

    try {
        $this->artisan('flux-icons:update', ['--vendor' => 'nope'])
            ->expectsOutputToContain('No built icons found for: nope')
            ->expectsOutputToContain('None of the requested vendors have built icons')
            ->assertExitCode(1);
    } finally {
        File::deleteDirectory($root);
    }
});
