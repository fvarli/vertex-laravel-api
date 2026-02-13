<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $storagePath = sys_get_temp_dir() . '/vertex-laravel-api-testing-storage';

        File::ensureDirectoryExists($storagePath);
        $this->app->useStoragePath($storagePath);
    }
}
