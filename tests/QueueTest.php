<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Tests;

use Appsur\FacturasIa\FacturaExtractor;
use Appsur\FacturasIa\Jobs\ExtractFacturaJob;
use Appsur\FacturasIa\Models\Document;
use Appsur\FacturasIa\Services\DocumentUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class QueueTest extends TestCase
{
    public function test_queue_from_pdf_uploads_and_dispatches_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $pdf = UploadedFile::fake()->create('f.pdf', 10, 'application/pdf');
        $document = app(FacturaExtractor::class)->queueFromPdf($pdf, 5);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame(1, Document::count());
        Queue::assertPushed(ExtractFacturaJob::class, fn ($job) => $job->document->is($document) && $job->userId === 5);
    }

    public function test_job_handle_extracts_the_document(): void
    {
        Storage::fake('local');
        Http::fake(['*/responses' => Http::response([
            'output_text' => json_encode([
                'proveedor' => ['nif' => 'ES-B1', 'nombre' => 'P'],
                'receptor' => ['nif' => 'ES-B2', 'nombre' => 'R'],
                'numero_factura' => 'Q-1', 'fecha' => '2026-06-30', 'portes' => null, 'total' => 121.0,
                'albaranes' => [['numero_albaran' => 'A1', 'items' => [['concepto' => 'x', 'cantidad' => 1, 'iva' => 21, 'precio' => 100, 'importe' => 100]]]],
            ]),
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15],
        ], 200)]);

        $pdf = UploadedFile::fake()->create('f.pdf', 10, 'application/pdf');
        $document = app(DocumentUploadService::class)->store($pdf);

        (new ExtractFacturaJob($document))->handle(app(FacturaExtractor::class));

        $this->assertNotNull($document->refresh()->factura);
        $this->assertSame('Q-1', $document->factura->numero);
    }
}
