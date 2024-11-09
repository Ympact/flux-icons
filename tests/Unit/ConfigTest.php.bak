<?php

namespace Ympact\FluxIcons\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testConfigFileExists()
    {
        $this->assertFileExists(__DIR__.'/../../config/flux-icons.php');
    }

    public function testConfigStructure()
    {
        $config = require __DIR__.'/../../config/flux-icons.php';

        $this->assertArrayHasKey('tabler', $config);
        $this->assertArrayHasKey('vendor_name', $config['tabler']);
        $this->assertArrayHasKey('package_name', $config['tabler']);
        $this->assertArrayHasKey('source_directories', $config['tabler']);
        $this->assertArrayHasKey('transform_svg_path', $config['tabler']);
        $this->assertArrayHasKey('change_stroke_width', $config['tabler']);
    }
}