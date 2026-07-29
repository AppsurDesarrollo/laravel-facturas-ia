<?php

namespace Appsur\FacturasIa;

use Appsur\FacturasIa\Events\ExtractionFailed;
use Appsur\FacturasIa\Events\FacturaExtracted;
use Appsur\FacturasIa\Exceptions\ExtractionException;
use Appsur\FacturasIa\Models\Document;
use Appsur\FacturasIa\Models\Factura;
use Appsur\FacturasIa\Services\DocumentUploadService;
use Appsur\FacturasIa\Services\ExtractionService;
use Appsur\FacturasIa\Services\FacturaNormalizer;
use Appsur\FacturasIa\Support\CuadreChecker;
use Appsur\FacturasIa\Support\OpenAiModelCatalog;
use Illuminate\Http\UploadedFile;

/**
 * Punto de entrada de una sola llamada: sube el PDF, lo extrae con el modelo por defecto,
 * reprocesa con el modelo de respaldo si no cuadra, normaliza y devuelve la Factura.
 */
class FacturaExtractor
{
    public function __construct(
        private DocumentUploadService $uploader,
        private ExtractionService $extractor,
    ) {}

    /** Sube un PDF (UploadedFile o ruta) y lo procesa de principio a fin. */
    public function fromPdf(UploadedFile|string $pdf, ?int $userId = null): Factura
    {
        $document = $this->uploader->store($pdf, $userId);

        return $this->extractDocument($document, $userId);
    }

    /** Procesa un documento ya subido: extrae (+ reproceso), normaliza y devuelve la Factura. */
    public function extractDocument(Document $document, ?int $userId = null): Factura
    {
        $default = (string) config('facturas-ia.default_model');
        $fallback = config('facturas-ia.fallback_model');

        $run = $this->extractor->run($document, $default, $userId);
        $best = $run;

        // Reproceso automático: si no cuadra y hay modelo de respaldo distinto, reintenta.
        if ($run->status === 'succeeded'
            && $fallback
            && $fallback !== $default
            && OpenAiModelCatalog::get($fallback)
            && ! CuadreChecker::cuadra($run->result_json)) {
            $fb = $this->extractor->run($document, $fallback, $userId);
            if ($fb->status === 'succeeded') {
                $best = $fb;
            }
        }

        if ($best->status !== 'succeeded' || ! is_array($best->result_json)) {
            event(new ExtractionFailed($document, $best));

            throw new ExtractionException($best->error ?: 'La extracción de la factura falló.');
        }

        FacturaNormalizer::fromRun($document, $best);

        $factura = $document->factura()->with(['proveedor', 'receptor', 'albaranes.items'])->first();

        event(new FacturaExtracted($factura, $best));

        return $factura;
    }
}
