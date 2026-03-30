<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Register package service providers for tests.
     *
     * @param  Application  $app
     * @return array<int,string>
     */
    protected function getPackageProviders($app)
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure base_path() resolves to the package root during tests
        $this->app->setBasePath(dirname(__DIR__));
    }
}
