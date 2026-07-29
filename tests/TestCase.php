<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Tests;

use Appsur\FacturasIa\FacturasIaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [FacturasIaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Base de datos SQLite en memoria (por defecto de testbench).
        $app['config']->set('database.default', 'testing');
        $app['config']->set('facturas-ia.openai.key', 'sk-test');
        $app['config']->set('facturas-ia.disk', 'local');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
