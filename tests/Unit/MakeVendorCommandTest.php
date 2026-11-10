<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Console\MakeVendorCommand;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Support\VendorRegistry;
use Ympact\FluxIcons\Vendors\Tabler;

// every test gets an application namespace of its own, so the classes it generates and
// loads never collide with another test's, also under --repeat
beforeEach(function () {
    $this->root = sys_get_temp_dir().'/flux-icons-make-vendor-'.Str::random(8);
    $this->namespace = 'Site'.Str::random(8);
    $this->vendors = "{$this->root}/app/FluxIcons/Vendors";

    File::ensureDirectoryExists("{$this->root}/app");
    File::put("{$this->root}/composer.json", json_encode(['autoload' => ['psr-4' => ["{$this->namespace}\\" => 'app/']]]));

    $this->app->setBasePath($this->root);
    $this->app[Kernel::class]->registerCommand($this->app->make(MakeVendorCommand::class));
    Config::set('flux-icons.vendors', ['tabler' => Tabler::class]);
});

afterEach(fn () => File::deleteDirectory($this->root));

it('generates a subclass of a registered vendor in the application namespace', function () {
    $this->artisan('flux-icons:make-vendor', ['name' => 'HouseStyle', '--extends' => 'tabler'])
        ->expectsOutputToContain("'tabler' => \\{$this->namespace}\\FluxIcons\\Vendors\\HouseStyle::class")
        ->assertExitCode(0);

    require "{$this->vendors}/HouseStyle.php";
    $class = "{$this->namespace}\\FluxIcons\\Vendors\\HouseStyle";

    expect(new $class)->toBeInstanceOf(Tabler::class);
});

it('generates a subclass that shares the name of the vendor it extends', function () {
    $this->artisan('flux-icons:make-vendor', ['name' => 'Tabler', '--extends' => 'tabler'])->assertExitCode(0);

    require "{$this->vendors}/Tabler.php";
    $class = "{$this->namespace}\\FluxIcons\\Vendors\\Tabler";

    expect(new $class)->toBeInstanceOf(Tabler::class);
});

it('generates a new vendor that answers every variant Flux asks for', function () {
    $this->artisan('flux-icons:make-vendor', ['name' => 'hand-drawn'])
        ->expectsOutputToContain("'hand-drawn' => \\{$this->namespace}\\FluxIcons\\Vendors\\HandDrawn::class")
        ->assertExitCode(0);

    require "{$this->vendors}/HandDrawn.php";
    $vendor = (new VendorRegistry(['hand-drawn' => "{$this->namespace}\\FluxIcons\\Vendors\\HandDrawn"]))->get('hand-drawn');

    expect($vendor)->toBeInstanceOf(Vendor::class)
        ->and($vendor->resolvedVariants()->missingFluxVariants())->toBe([]);
});

it('does not overwrite a vendor class that exists', function () {
    File::ensureDirectoryExists($this->vendors);
    File::put("{$this->vendors}/Existing.php", '<?php // adjusted');

    $this->artisan('flux-icons:make-vendor', ['name' => 'Existing', '--extends' => 'tabler'])
        ->expectsOutputToContain('already exists');

    expect(File::get("{$this->vendors}/Existing.php"))->toBe('<?php // adjusted');
});

it('overwrites a vendor class that exists when forced', function () {
    File::ensureDirectoryExists($this->vendors);
    File::put("{$this->vendors}/Existing.php", '<?php // adjusted');

    $this->artisan('flux-icons:make-vendor', ['name' => 'Existing', '--extends' => 'tabler', '--force' => true])->assertExitCode(0);

    expect(File::get("{$this->vendors}/Existing.php"))->toContain('class Existing extends Tabler');
});

it('refuses to replace the registered vendor it would extend, even when forced', function () {
    $this->artisan('flux-icons:make-vendor', ['name' => 'HouseStyle', '--extends' => 'tabler'])->assertExitCode(0);
    require "{$this->vendors}/HouseStyle.php";
    $class = "{$this->namespace}\\FluxIcons\\Vendors\\HouseStyle";
    Config::set('flux-icons.vendors', ['tabler' => Tabler::class, 'house' => $class]);

    $this->artisan('flux-icons:make-vendor', ['name' => 'HouseStyle', '--extends' => 'house', '--force' => true])
        ->expectsOutputToContain("HouseStyle cannot extend itself: 'house' is registered as {$class}.");

    expect(File::get("{$this->vendors}/HouseStyle.php"))->toContain('class HouseStyle extends Tabler');
});

it('fails for a vendor to extend that is not registered', function () {
    $this->artisan('flux-icons:make-vendor', ['name' => 'HouseStyle', '--extends' => 'nope'])
        ->expectsOutputToContain("Vendor configuration for 'nope' not found.");

    expect("{$this->vendors}/HouseStyle.php")->not->toBeFile();
});

// like Laravel's make commands, a refusal prints why and exits with 0
it('refuses a name that cannot be a class name', function (string $name, string $reason) {
    $this->artisan('flux-icons:make-vendor', ['name' => $name])
        ->expectsOutputToContain($reason);

    expect($this->vendors)->not->toBeDirectory();
})->with([
    'starting with a digit' => ['3d-icons', "'3d-icons' is not a valid class name."],
    'reserved by PHP' => ['list', 'The name "List" is reserved by PHP.'],
    'imported by the new vendor' => ['template', 'would clash with the Template class it imports'],
]);

it('says to publish the config when the application has none', function () {
    $this->artisan('flux-icons:make-vendor', ['name' => 'HouseStyle', '--extends' => 'tabler'])
        ->expectsOutputToContain('php artisan vendor:publish --tag=flux-icons-config')
        ->assertExitCode(0);
});

it('does not ask to publish a config the application already has', function () {
    File::ensureDirectoryExists("{$this->root}/config");
    File::put("{$this->root}/config/flux-icons.php", '<?php return [];');

    $this->artisan('flux-icons:make-vendor', ['name' => 'HouseStyle', '--extends' => 'tabler'])
        ->doesntExpectOutputToContain('vendor:publish')
        ->assertExitCode(0);
});
