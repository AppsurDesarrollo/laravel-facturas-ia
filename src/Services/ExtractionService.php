<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Services;

use Appsur\FacturasIa\Models\Document;
use Appsur\FacturasIa\Models\ExtractionRun;
use Appsur\FacturasIa\Support\OpenAiModelCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ejecuta una extracción de una factura (Document) con un modelo de OpenAI usando
 * la Responses API: envía el PDF directamente (visión) y fuerza un JSON estructurado
 * (structured outputs, strict). Persiste un ExtractionRun con el resultado, los tokens,
 * el coste y la latencia.
 */
class ExtractionService
{
    public function __construct(private ExtractionSchemaBuilder $schemaBuilder) {}

    public function run(Document $document, string $modelId, ?int $userId = null): ExtractionRun
    {
        $model = OpenAiModelCatalog::get($modelId);

        $run = ExtractionRun::create([
            'document_id' => $document->id,
            'user_id' => $userId,
            'model' => $modelId,
            'status' => 'running',
        ]);

        $startedAt = hrtime(true);

        try {
            if (! $model) {
                return $this->fail($run, $startedAt, 'El modelo no está en el catálogo (config facturas-ia.models).');
            }
            if (! $model['pdf']) {
                return $this->fail($run, $startedAt, 'El modelo no admite lectura de PDF (visión).');
            }

            $apiKey = $this->resolveApiKey();
            if (! $apiKey) {
                return $this->fail($run, $startedAt, 'Falta la clave de OpenAI (OPENAI_API_KEY).');
            }

            $pdf = Storage::disk($document->disk)->get($document->path);
            if ($pdf === null) {
                return $this->fail($run, $startedAt, 'No se encontró el archivo PDF.');
            }

            $schema = $this->schemaBuilder->build();

            $payload = [
                'model' => $modelId,
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_file',
                            'filename' => $document->original_name,
                            'file_data' => 'data:application/pdf;base64,'.base64_encode($pdf),
                        ],
                        [
                            'type' => 'input_text',
                            'text' => (string) config('facturas-ia.prompt'),
                        ],
                    ],
                ]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'factura',
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
            ];

            if ($model['reasoning']) {
                $payload['reasoning'] = ['effort' => 'low'];
                $payload['max_output_tokens'] = 16000;
            } else {
                $payload['temperature'] = 0;
            }

            $base = rtrim((string) config('facturas-ia.openai.base_url', 'https://api.openai.com/v1'), '/');

            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->connectTimeout(15)
                ->retry(1, 500, throw: false)
                ->acceptJson()
                ->post($base.'/responses', $payload);

            if ($response->failed()) {
                $body = $response->json();
                $message = data_get($body, 'error.message', 'Error HTTP '.$response->status());
                Log::warning('OpenAI extraction failed', ['status' => $response->status(), 'body' => $body]);

                return $this->fail($run, $startedAt, $this->sanitize($message, $response->status()));
            }

            $data = $response->json();
            $text = $this->extractText($data);
            $decoded = $text !== null ? json_decode($text, true) : null;
            if (is_array($decoded)) {
                $decoded = $this->cleanFloats($decoded);
            }

            $usage = $data['usage'] ?? [];
            $prompt = (int) ($usage['input_tokens'] ?? 0);
            $completion = (int) ($usage['output_tokens'] ?? 0);
            $total = (int) ($usage['total_tokens'] ?? ($prompt + $completion));
            $cached = (int) data_get($usage, 'input_tokens_details.cached_tokens', 0);
            $reasoning = data_get($usage, 'output_tokens_details.reasoning_tokens');

            $cost = $this->computeCost($model, $prompt, $completion, $cached);

            $ok = is_array($decoded);

            $run->update([
                'status' => $ok ? 'succeeded' : 'failed',
                'result_json' => $ok ? $decoded : null,
                'raw_output' => $text,
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'total_tokens' => $total,
                'cached_tokens' => $cached,
                'reasoning_tokens' => $reasoning !== null ? (int) $reasoning : null,
                'cost_usd' => $cost,
                'latency_ms' => $this->elapsedMs($startedAt),
                'error' => $ok ? null : 'La respuesta del modelo no era un JSON válido.',
            ]);

            return $run->fresh();
        } catch (Throwable $e) {
            Log::error('OpenAI extraction exception', ['message' => $e->getMessage()]);

            return $this->fail($run, $startedAt, 'Error de conexión con OpenAI.');
        }
    }

    public function resolveApiKey(): ?string
    {
        return config('facturas-ia.openai.key') ?: null;
    }

    /**
     * Elimina el ruido de coma flotante (668.4199999999998 → 668.42), manteniendo hasta
     * 6 decimales reales. Las facturas no necesitan más precisión.
     */
    private function cleanFloats($value)
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->cleanFloats($v), $value);
        }
        if (is_float($value)) {
            return round($value, 6);
        }

        return $value;
    }

    /** Recorre output[] buscando el texto de la respuesta (Responses API). */
    private function extractText(array $data): ?string
    {
        if (! empty($data['output_text']) && is_string($data['output_text'])) {
            return $data['output_text'];
        }

        foreach ($data['output'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'output_text') {
                    return $part['text'] ?? null;
                }
            }
        }

        return null;
    }

    private function computeCost(array $model, int $prompt, int $completion, int $cached): float
    {
        $inputPrice = (float) $model['in'];
        $outputPrice = (float) $model['out'];
        $cachedPrice = $model['cached'] !== null ? (float) $model['cached'] : $inputPrice;

        $uncached = max(0, $prompt - $cached);

        $inputCost = $uncached / 1_000_000 * $inputPrice + $cached / 1_000_000 * $cachedPrice;
        $outputCost = $completion / 1_000_000 * $outputPrice;

        return round($inputCost + $outputCost, 6);
    }

    private function fail(ExtractionRun $run, int $startedAt, string $message): ExtractionRun
    {
        $run->update([
            'status' => 'failed',
            'error' => $message,
            'latency_ms' => $this->elapsedMs($startedAt),
        ]);

        return $run->fresh();
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }

    private function sanitize(string $message, int $status): string
    {
        $message = trim((string) $message);

        return $message !== '' ? "OpenAI ($status): $message" : "Error HTTP $status de OpenAI.";
    }
}
