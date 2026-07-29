<?php

declare(strict_types=1);

namespace Appsur\FacturasIa;

use Appsur\FacturasIa\Events\ExtractionFailed;
use Appsur\FacturasIa\Events\FacturaExtracted;
use Appsur\FacturasIa\Exceptions\ExtractionException;
use Appsur\FacturasIa\Jobs\ExtractFacturaJob;
use Appsur\FacturasIa\Models\Document;
use Appsur\FacturasIa\Models\ExtractionRun;
use Appsur\FacturasIa\Models\Factura;
use Appsur\FacturasIa\Services\DocumentUploadService;
use Appsur\FacturasIa\Services\ExtractionService;
use Appsur\FacturasIa\Services\FacturaNormalizer;
use Appsur\FacturasIa\Support\CuadreChecker;
use Appsur\FacturasIa\Support\OpenAiModelCatalog;
use Illuminate\Http\UploadedFile;

/**
 * Punto de entrada de una sola llamada: sube el PDF, lo extrae con el modelo por defecto,
 * reprocesa con el modelo de respaldo si falla o no cuadra, normaliza y devuelve la Factura.
 */
class FacturaExtractor
{
    public function __construct(
        private DocumentUploadService $uploader,
        private ExtractionService $extractor,
    ) {}

    /**
     * Sube un PDF (UploadedFile o ruta) y lo procesa de principio a fin (síncrono).
     *
     * @param  ?string  $tipo  'recibida' | 'emitida' para forzar la dirección; null autodetecta por NIF.
     */
    public function fromPdf(UploadedFile|string $pdf, ?int $userId = null, ?string $tipo = null): Factura
    {
        $this->assertConfigured();

        // Dedupe: si ya se extrajo un PDF idéntico, se devuelve esa factura sin gastar OpenAI.
        if (config('facturas-ia.dedupe', true)) {
            $existing = $this->existingFacturaByHash(DocumentUploadService::hashFor($pdf));
            if ($existing !== null) {
                return $existing;
            }
        }

        $document = $this->uploader->store($pdf, $userId);

        return $this->extractDocument($document, $userId, $tipo);
    }

    /** Sube el PDF (rápido) y despacha la extracción a la cola; devuelve el Document. */
    public function queueFromPdf(UploadedFile|string $pdf, ?int $userId = null, ?string $tipo = null): Document
    {
        $this->assertConfigured();

        if (config('facturas-ia.dedupe', true)) {
            $existing = $this->existingFacturaByHash(DocumentUploadService::hashFor($pdf));
            if ($existing !== null) {
                return $existing->document;
            }
        }

        $document = $this->uploader->store($pdf, $userId);
        ExtractFacturaJob::dispatch($document, $userId, $tipo);

        return $document;
    }

    /** Procesa un documento ya subido: extrae (+ reproceso), normaliza y devuelve la Factura. */
    public function extractDocument(Document $document, ?int $userId = null, ?string $tipo = null): Factura
    {
        $this->assertConfigured();

        $default = (string) config('facturas-ia.default_model');
        $fallback = config('facturas-ia.fallback_model');

        $run = $this->extractor->run($document, $default, $userId);
        $best = $run;

        // Reproceso con el modelo de respaldo si el primero FALLA o NO cuadra.
        $needsFallback = $run->status !== 'succeeded' || ! CuadreChecker::cuadra($run->result_json);

        if ($needsFallback
            && is_string($fallback) && $fallback !== '' && $fallback !== $default
            && OpenAiModelCatalog::get($fallback) !== null) {
            $fb = $this->extractor->run($document, $fallback, $userId);
            $best = $this->pickBest($run, $fb);
        }

        if ($best->status !== 'succeeded' || ! is_array($best->result_json)) {
            event(new ExtractionFailed($document, $best));

            throw new ExtractionException($best->error ?: 'La extracción de la factura falló.');
        }

        FacturaNormalizer::fromRun($document, $best, $tipo);

        $factura = $document->factura()->with(['proveedor', 'receptor', 'albaranes.items'])->first();

        event(new FacturaExtracted($factura, $best));

        return $factura;
    }

    /** El mejor de dos runs: prioriza el éxito; entre dos con éxito, el que cuadre. */
    private function pickBest(ExtractionRun $primary, ExtractionRun $fallback): ExtractionRun
    {
        if ($fallback->status !== 'succeeded') {
            return $primary;
        }
        if ($primary->status !== 'succeeded') {
            return $fallback;
        }

        // Ambos con éxito: si el primario ya cuadraba no habríamos reprocesado, así que
        // aquí el primario no cuadra → nos quedamos con el intento del modelo de respaldo.
        return CuadreChecker::cuadra($primary->result_json) ? $primary : $fallback;
    }

    private function existingFacturaByHash(string $hash): ?Factura
    {
        $document = Document::query()->where('hash', $hash)->whereHas('factura')->latest('id')->first();

        return $document?->factura()->with(['proveedor', 'receptor', 'albaranes.items'])->first();
    }

    private function assertConfigured(): void
    {
        if (! config('facturas-ia.openai.key')) {
            throw new ExtractionException('Falta la clave de OpenAI (OPENAI_API_KEY / config facturas-ia.openai.key).');
        }

        $default = (string) config('facturas-ia.default_model');
        if (OpenAiModelCatalog::get($default) === null) {
            throw new ExtractionException("El modelo por defecto '{$default}' no está en el catálogo (config facturas-ia.models).");
        }
    }
}
