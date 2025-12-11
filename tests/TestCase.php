<?php

namespace Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure base_path() resolves to the package root during tests
        $this->app->instance('path.base', dirname(__DIR__));
    }
}
