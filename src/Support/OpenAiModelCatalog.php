<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Support;

/**
 * Catálogo de modelos de OpenAI: precios (USD por 1M de tokens) y capacidades.
 * Se lee de config('facturas-ia.models'), editable en el proyecto.
 */
class OpenAiModelCatalog
{
    /** @return array<string, array{label:string,in:float,out:float,cached:?float,pdf:bool,reasoning:bool,sort:int}> */
    public static function priceMap(): array
    {
        return (array) config('facturas-ia.models', []);
    }

    /**
     * Lista completa ordenada; cada entrada incluye su model_id.
     *
     * @return array<int, array{model_id:string,label:string,in:float,out:float,cached:?float,pdf:bool,reasoning:bool,sort:int}>
     */
    public static function all(): array
    {
        $out = [];
        foreach (self::priceMap() as $id => $m) {
            $out[] = ['model_id' => $id] + $m;
        }
        usort($out, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return $out;
    }

    /** Metadatos de un modelo por su id (con model_id), o null si no está en el catálogo. */
    public static function get(string $modelId): ?array
    {
        $m = self::priceMap()[$modelId] ?? null;

        return $m ? (['model_id' => $modelId] + $m) : null;
    }

    public static function isKnown(string $modelId): bool
    {
        return isset(self::priceMap()[$modelId]);
    }
}
