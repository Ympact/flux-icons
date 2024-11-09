<?php

namespace Tests\Feature;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Ympact\FluxIcons\Services\IconBuilder;

class IconBuilderTest extends TestCase
{
    protected $iconBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock the config
        // use /tests/config/flux-icons.php config file as a template
        $file = __DIR__ . '/../config/flux-icons.php';
        Config::set('flux-icons', require $file);
       
        $this->iconBuilder = new IconBuilder();
    }

    public function testGetAvailableVendors()
    {
        $vendors = IconBuilder::getAvailableVendors();

        $this->assertInstanceOf(Collection::class, $vendors);
        $this->assertCount(4, $vendors);
        $this->assertEquals(['alpha', 'beta', 'gamma', 'epsilon'], array_keys($vendors->toArray()));
    }

    
    public function testGetAvailableIcons()
    {
        $this->iconBuilder->setVendor('alpha');

        // Mock the config and file system
        //Config::set('flux-icons.variants.outline.source', 'path/to/icons');
        File::shouldReceive('glob')
            ->once()
            ->with(Str::of(base_path('tests/vendor/alpha/icons'))->finish('/') . '*.svg')
            ->andReturn(['icon1.svg', 'icon2.svg']);

        $icons = $this->iconBuilder->getAvailableIcons('outline');

        $this->assertInstanceOf(Collection::class, $icons);
        $this->assertCount(2, $icons);
        $this->assertEquals(['icon1.svg', 'icon2.svg'], $icons->toArray());
    }
    
}