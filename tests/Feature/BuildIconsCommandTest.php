<?php

namespace FluxIcons\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use FluxIcons\FluxIconsServiceProvider;
use FluxIcons\Services\IconBuilder;
use Mockery;

class BuildIconsCommandTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            FluxIconsServiceProvider::class,
        ];
    }

    public function testBuildIconsCommand()
    {
        // Mock the npm install command
        $this->mockProcess(['npm', 'install', '@tabler/icons', '--save']);

        // Mock the file system interactions
        $files = Mockery::mock('Illuminate\Filesystem\Filesystem');
        $files->shouldReceive('exists')->andReturn(true);
        $files->shouldReceive('makeDirectory')->andReturn(true);
        $files->shouldReceive('files')->andReturn([__DIR__.'/dummy.svg']);
        $files->shouldReceive('get')->andReturn('<svg><path d="M10 10"/></svg>');
        $files->shouldReceive('put')->andReturn(true);

        // Bind the mock to the container
        $this->app->instance('files', $files);

        // Run the command
        Artisan::call('flux-icons:build', ['vendor' => 'tabler']);

        // Check if the output directory exists
        $outputDir = resource_path('views/flux/icon/Tabler');
        $this->assertDirectoryExists($outputDir);

        // Check if at least one Blade file is generated
        $files = File::files($outputDir);
        $this->assertNotEmpty($files);
    }

    public function testBuildIconsCommandWithSingleIcon()
    {
        // Mock the npm install command
        $this->mockProcess(['npm', 'install', '@tabler/icons', '--save']);

        // Mock the file system interactions
        $files = Mockery::mock('Illuminate\Filesystem\Filesystem');
        $files->shouldReceive('exists')->andReturn(true);
        $files->shouldReceive('makeDirectory')->andReturn(true);
        $files->shouldReceive('files')->andReturn([__DIR__.'/dummy.svg']);
        $files->shouldReceive('get')->andReturn('<svg><path d="M10 10"/></svg>');
        $files->shouldReceive('put')->andReturn(true);

        // Bind the mock to the container
        $this->app->instance('files', $files);

        // Run the command with a single icon
        $output = Artisan::call('flux-icons:build', ['vendor' => 'tabler', 'icons' => 'icon1']);

        // Check if the output directory exists
        $outputDir = resource_path('views/flux/icon/Tabler');
        $this->assertDirectoryExists($outputDir);

        // Check if at least one Blade file is generated
        $files = File::files($outputDir);
        $this->assertNotEmpty($files);

        // Check the command output
        $this->assertStringContainsString('Icons built successfully for vendor: tabler', Artisan::output());
    }

    public function testBuildIconsCommandWithMultipleIcons()
    {
        // Mock the npm install command
        $this->mockProcess(['npm', 'install', '@tabler/icons', '--save']);

        // Mock the file system interactions
        $files = Mockery::mock('Illuminate\Filesystem\Filesystem');
        $files->shouldReceive('exists')->andReturn(true);
        $files->shouldReceive('makeDirectory')->andReturn(true);
        $files->shouldReceive('files')->andReturn([__DIR__.'/dummy1.svg', __DIR__.'/dummy2.svg']);
        $files->shouldReceive('get')->andReturn('<svg><path d="M10 10"/></svg>');
        $files->shouldReceive('put')->andReturn(true);

        // Bind the mock to the container
        $this->app->instance('files', $files);

        // Run the command with multiple icons
        $output = Artisan::call('flux-icons:build', ['vendor' => 'tabler', 'icons' => 'icon1,icon2']);

        // Check if the output directory exists
        $outputDir = resource_path('views/flux/icon/Tabler');
        $this->assertDirectoryExists($outputDir);

        // Check if at least one Blade file is generated
        $files = File::files($outputDir);
        $this->assertNotEmpty($files);

        // Check the command output
        $this->assertStringContainsString('Icons built successfully for vendor: tabler', Artisan::output());
    }

    protected function mockProcess(array $command)
    {
        $process = Mockery::mock('overload:Symfony\Component\Process\Process');
        $process->shouldReceive('setWorkingDirectory')->andReturnSelf();
        $process->shouldReceive('run')->andReturnSelf();
        $process->shouldReceive('isSuccessful')->andReturn(true);
        $process->shouldReceive('getOutput')->andReturn('Mocked process output');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}