<?php

use App\FluxIcons\Vendors\HandDrawn;
use App\FluxIcons\Vendors\HouseStyle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Console\MakeVendorCommand;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Support\VendorRegistry;
use Ympact\FluxIcons\Vendors\Tabler;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/flux-icons-make-vendor-'.Str::random(8);
    File::ensureDirectoryExists($this->root);

    $this->app->setBasePath($this->root);
    $this->app[Kernel::class]->registerCommand(new MakeVendorCommand);
    Config::set('flux-icons.vendors', ['tabler' => Tabler::class]);
});

afterEach(fn () => File::deleteDirectory($this->root));

it('generates a subclass of a registered vendor', function () {
    $this->artisan('flux-icons:make-vendor', ['name' => 'HouseStyle', '--extends' => 'tabler'])
        ->expectsOutputToContain("'tabler' => \\App\\FluxIcons\\Vendors\\HouseStyle::class")
        ->assertExitCode(0);

    require "{$this->root}/app/FluxIcons/Vendors/HouseStyle.php";

    expect(new HouseStyle)->toBeInstanceOf(Tabler::class);
});

it('generates a subclass that shares the name of the vendor it extends', function () {
    $this->artisan('flux-icons:make-vendor', ['name' => 'Tabler', '--extends' => 'tabler'])->assertExitCode(0);

    require "{$this->root}/app/FluxIcons/Vendors/Tabler.php";

    expect(new App\FluxIcons\Vendors\Tabler)->toBeInstanceOf(Tabler::class);
});

it('generates a new vendor that answers every variant Flux asks for', function () {
    $this->artisan('flux-icons:make-vendor', ['name' => 'hand-drawn'])
        ->expectsOutputToContain("'hand-drawn' => \\App\\FluxIcons\\Vendors\\HandDrawn::class")
        ->assertExitCode(0);

    require "{$this->root}/app/FluxIcons/Vendors/HandDrawn.php";
    $vendor = (new VendorRegistry(['hand-drawn' => HandDrawn::class]))->get('hand-drawn');

    expect($vendor)->toBeInstanceOf(Vendor::class)
        ->and($vendor->resolvedVariants()->missingFluxVariants())->toBe([]);
});

it('does not overwrite a vendor class that exists', function () {
    File::ensureDirectoryExists("{$this->root}/app/FluxIcons/Vendors");
    File::put("{$this->root}/app/FluxIcons/Vendors/Existing.php", '<?php // adjusted');

    $this->artisan('flux-icons:make-vendor', ['name' => 'Existing', '--extends' => 'tabler'])->assertExitCode(1);

    expect(File::get("{$this->root}/app/FluxIcons/Vendors/Existing.php"))->toBe('<?php // adjusted');
});

it('fails for a vendor to extend that is not registered', function () {
    $this->artisan('flux-icons:make-vendor', ['name' => 'HouseStyle', '--extends' => 'nope'])
        ->expectsOutputToContain("Vendor configuration for 'nope' not found.")
        ->assertExitCode(1);

    expect("{$this->root}/app/FluxIcons/Vendors/HouseStyle.php")->not->toBeFile();
});

it('refuses a name that cannot be a class name', function () {
    $this->artisan('flux-icons:make-vendor', ['name' => '3d-icons'])->assertExitCode(1);

    expect("{$this->root}/app")->not->toBeDirectory();
});
