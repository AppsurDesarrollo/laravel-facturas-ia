<?php

declare(strict_types=1);

namespace Appsur\FacturasIa;

use Appsur\FacturasIa\Settings\FacturasIaSettings;
use Illuminate\Support\ServiceProvider;

class FacturasIaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/facturas-ia.php', 'facturas-ia');

        $this->app->singleton(FacturaExtractor::class);
        $this->app->bind('facturas-ia', fn ($app) => $app->make(FacturaExtractor::class));

        $this->registerSettings();
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/facturas-ia.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/facturas-ia.php' => config_path('facturas-ia.php'),
            ], 'facturas-ia-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'facturas-ia-migrations');

            $this->publishes([
                __DIR__.'/../database/settings' => database_path('settings'),
            ], 'facturas-ia-settings');

            $this->publishes([
                __DIR__.'/../resources/js/pages/facturas-ia' => resource_path('js/pages/facturas-ia'),
            ], 'facturas-ia-views');
        }
    }

    /**
     * Registra la clase de settings y su ruta de migración en spatie/laravel-settings
     * de forma robusta al orden de carga de providers (defiende el default del host).
     */
    private function registerSettings(): void
    {
        $classes = config('settings.settings', []);
        $classes[] = FacturasIaSettings::class;
        config(['settings.settings' => array_values(array_unique($classes))]);

        $paths = config('settings.migrations_paths', [database_path('settings')]);
        $paths[] = __DIR__.'/../database/settings';
        config(['settings.migrations_paths' => array_values(array_unique($paths))]);
    }
}
