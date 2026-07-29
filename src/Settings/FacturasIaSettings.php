<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Settings;

use Spatie\LaravelSettings\Settings;
use Throwable;

/**
 * Ajustes editables del paquete, guardados en BD (tabla `settings`, grupo `facturas-ia`)
 * y configurables desde el panel de Ajustes de la app. Los valores por defecto se siembran
 * desde config/facturas-ia.php en la migración de settings.
 */
class FacturasIaSettings extends Settings
{
    public string $prompt = '';

    public string $defaultModel = 'gpt-5.4-mini';

    public ?string $fallbackModel = 'gpt-4.1';

    public array $ownNifs = [];

    public bool $dedupe = true;

    public array $fields = [];

    public float $cuadreToleranceAbs = 0.5;

    public float $cuadreTolerancePct = 0.01;

    public array $cuadreIvaRates = [0, 4, 10, 21];

    public array $models = [];

    public ?string $openaiKey = null;

    public ?string $openaiAdminKey = null;

    public ?string $openaiProjectId = null;

    public string $openaiBaseUrl = 'https://api.openai.com/v1';

    public static function group(): string
    {
        return 'facturas-ia';
    }

    /**
     * Resuelve los ajustes de BD de forma segura. Si spatie no está listo o la tabla
     * `settings` aún no está migrada, devuelve null para que el llamador caiga a config.
     */
    public static function resolve(): ?self
    {
        try {
            return app(self::class);
        } catch (Throwable) {
            return null;
        }
    }
}
