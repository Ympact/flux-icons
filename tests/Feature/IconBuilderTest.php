<?php

namespace Tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase;
use Ympact\FluxIcons\Services\IconBuilder;

class IconBuilderTest extends TestCase
{
    protected $iconBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock the config
        // use /tests/config/flux-icons.php config file as a template
        $file = __DIR__.'/../config/flux-icons.php';
        Config::set('flux-icons', require $file);

        $this->iconBuilder = new IconBuilder;
    }

    public function test_get_available_vendors()
    {
        $vendors = IconBuilder::getAvailableVendors();

        $this->assertInstanceOf(Collection::class, $vendors);
        $this->assertCount(4, $vendors);
        $this->assertEquals(['alpha', 'beta', 'gamma', 'epsilon'], array_keys($vendors->toArray()));
    }

    public function test_get_available_icons()
    {
        $this->iconBuilder->setVendor('alpha');

        // Mock the config and file system
        // Config::set('flux-icons.variants.outline.source', 'path/to/icons');
        File::shouldReceive('glob')
            ->once()
            ->withArgs(function ($arg) {
                $path = (string) $arg;
                // Accept paths that contain the test vendor dir and end with svg pattern
                return str_contains($path, 'tests/vendor/alpha/icons') && str_ends_with($path, 'outline/*.svg');
            })
            ->andReturn(['icon1.svg', 'icon2.svg']);

        $icons = $this->iconBuilder->getAvailableIcons('outline');

        $this->assertInstanceOf(Collection::class, $icons);
        $this->assertCount(2, $icons);
        $this->assertEquals(['icon1.svg', 'icon2.svg'], $icons->toArray());
    }
}
