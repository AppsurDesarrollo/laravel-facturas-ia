<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Siembra los ajustes del paquete en la tabla `settings` (grupo `facturas-ia`) tomando
 * los valores por defecto de config/facturas-ia.php. A partir de aquí se editan en el panel.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $c = fn (string $key, $default = null) => config("facturas-ia.$key", $default);

        $this->migrator->add('facturas-ia.prompt', (string) $c('prompt', ''));
        $this->migrator->add('facturas-ia.defaultModel', (string) $c('default_model', 'gpt-5.4-mini'));
        $this->migrator->add('facturas-ia.fallbackModel', $c('fallback_model', 'gpt-4.1'));
        $this->migrator->add('facturas-ia.ownNifs', array_values((array) $c('own_nifs', [])));
        $this->migrator->add('facturas-ia.dedupe', (bool) $c('dedupe', true));
        $this->migrator->add('facturas-ia.fields', (array) $c('fields', []));
        $this->migrator->add('facturas-ia.cuadreToleranceAbs', (float) $c('cuadre.tolerance_abs', 0.5));
        $this->migrator->add('facturas-ia.cuadreTolerancePct', (float) $c('cuadre.tolerance_pct', 0.01));
        $this->migrator->add('facturas-ia.cuadreIvaRates', (array) $c('cuadre.iva_rates', [0, 4, 10, 21]));
        $this->migrator->add('facturas-ia.models', (array) $c('models', []));
        $this->migrator->add('facturas-ia.openaiKey', $c('openai.key'));
        $this->migrator->add('facturas-ia.openaiAdminKey', $c('openai.admin_key'));
        $this->migrator->add('facturas-ia.openaiProjectId', $c('openai.project_id'));
        $this->migrator->add('facturas-ia.openaiBaseUrl', (string) $c('openai.base_url', 'https://api.openai.com/v1'));
    }
};
