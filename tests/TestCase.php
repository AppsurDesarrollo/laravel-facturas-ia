<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Tests;

use Appsur\FacturasIa\FacturasIaServiceProvider;
use Inertia\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelSettingsServiceProvider::class,
            ServiceProvider::class,
            FacturasIaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode('12345678901234567890123456789012'));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('facturas-ia.openai.key', 'sk-test');
        $app['config']->set('facturas-ia.disk', 'local');
        $app['config']->set('facturas-ia.routes.middleware', ['web']); // sin 'auth' en tests
        $app['config']->set('settings.cache.enabled', false);
        // Vista raíz mínima para que Inertia renderice en los tests del controlador.
        $app['config']->set('view.paths', array_merge((array) $app['config']->get('view.paths', []), [__DIR__.'/views']));
    }

    protected function defineDatabaseMigrations(): void
    {
        // Tabla `settings` de spatie (solo para tests; en prod se publica del stub).
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Tablas de dominio del paquete.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Migración de settings del paquete (siembra grupo facturas-ia desde config).
        $this->loadMigrationsFrom(__DIR__.'/../database/settings');
    }
}
