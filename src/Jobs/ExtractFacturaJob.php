<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Jobs;

use Appsur\FacturasIa\FacturaExtractor;
use Appsur\FacturasIa\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Extrae una factura ya subida (Document) en segundo plano, para no bloquear la request
 * web durante los ~10-20s de OpenAI. Los eventos FacturaExtracted / ExtractionFailed se
 * disparan dentro de FacturaExtractor, así que el resultado (o el fallo) es observable.
 */
class ExtractFacturaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public ?int $userId = null,
        public ?string $tipo = null,
    ) {}

    public function handle(FacturaExtractor $extractor): void
    {
        $extractor->extractDocument($this->document, $this->userId, $this->tipo);
    }
}
