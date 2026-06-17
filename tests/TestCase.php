<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable\Tests;

use Kisame76\FilamentTreeTable\FilamentTreeTableServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentTreeTableServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }
}
