<?php

declare(strict_types=1);

namespace Appsur\FacturasIa;

use Illuminate\Support\ServiceProvider;

class FacturasIaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/facturas-ia.php', 'facturas-ia');

        $this->app->singleton(FacturaExtractor::class);
        $this->app->bind('facturas-ia', fn ($app) => $app->make(FacturaExtractor::class));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/facturas-ia.php' => config_path('facturas-ia.php'),
            ], 'facturas-ia-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'facturas-ia-migrations');
        }
    }
}
