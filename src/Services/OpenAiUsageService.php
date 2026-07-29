<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Services;

use Appsur\FacturasIa\Settings\FacturasIaSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lee la Usage API y la Cost API de OpenAI (nivel organización). Requiere una ADMIN key
 * (sk-admin-…), no la project key. Solo para el panel de gastos (opcional).
 *
 * Cost API:  GET /v1/organization/costs
 * Usage API: GET /v1/organization/usage/completions
 */
class OpenAiUsageService
{
    public function resolveAdminKey(): ?string
    {
        return FacturasIaSettings::resolve()?->openaiAdminKey ?: (config('facturas-ia.openai.admin_key') ?: null);
    }

    public function hasAdminKey(): bool
    {
        return $this->resolveAdminKey() !== null;
    }

    public function projectId(): ?string
    {
        return FacturasIaSettings::resolve()?->openaiProjectId ?: (config('facturas-ia.openai.project_id') ?: null);
    }

    /** Coste por bucket diario desde $startTime (unix seconds), desglosado por line_item. */
    public function costs(int $startTime): array
    {
        return $this->get('/organization/costs', array_filter([
            'start_time' => $startTime,
            'bucket_width' => '1d',
            'group_by' => 'line_item',
            'project_ids' => $this->projectId(),
            'limit' => 31,
        ], fn ($v) => $v !== null));
    }

    /** Uso de tokens agrupado por modelo desde $startTime. */
    public function usageByModel(int $startTime): array
    {
        return $this->get('/organization/usage/completions', array_filter([
            'start_time' => $startTime,
            'bucket_width' => '1d',
            'group_by' => 'model',
            'project_ids' => $this->projectId(),
            'limit' => 31,
        ], fn ($v) => $v !== null));
    }

    /**
     * @return array{data?: array, error?: string, message?: ?string}
     */
    private function get(string $path, array $params): array
    {
        $key = $this->resolveAdminKey();
        if (! $key) {
            return ['error' => 'no_admin_key'];
        }

        // OpenAI espera claves repetidas para arrays (group_by=model), no group_by[]=model.
        $qs = [];
        foreach ($params as $k => $v) {
            $qs[] = urlencode($k).'='.urlencode((string) $v);
        }
        $base = rtrim((string) (FacturasIaSettings::resolve()?->openaiBaseUrl ?: config('facturas-ia.openai.base_url', 'https://api.openai.com/v1')), '/');
        $url = $base.$path.'?'.implode('&', $qs);

        try {
            $res = Http::withToken($key)->timeout(30)->acceptJson()->get($url);

            if ($res->failed()) {
                Log::warning('OpenAI usage/cost API failed', ['status' => $res->status(), 'path' => $path]);

                return ['error' => 'http_'.$res->status(), 'message' => data_get($res->json(), 'error.message')];
            }

            return ['data' => $res->json('data', [])];
        } catch (Throwable $e) {
            Log::error('OpenAI usage/cost API exception', ['message' => $e->getMessage()]);

            return ['error' => 'exception'];
        }
    }
}
