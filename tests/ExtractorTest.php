<?php

namespace Appsur\FacturasIa\Tests;

use Appsur\FacturasIa\Events\FacturaExtracted;
use Appsur\FacturasIa\FacturaExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ExtractorTest extends TestCase
{
    private function factura(float $total): array
    {
        return [
            'proveedor' => ['nif' => 'ES-B1', 'nombre' => 'P'],
            'receptor' => ['nif' => 'ES-B2', 'nombre' => 'R'],
            'numero_factura' => 'F-1',
            'fecha' => '2026-06-30',
            'portes' => null,
            'total' => $total,
            'albaranes' => [[
                'numero_albaran' => 'A1',
                'items' => [['concepto' => 'x', 'cantidad' => 1, 'iva' => 21, 'precio' => 100, 'importe' => 100]],
            ]],
        ];
    }

    /** Respuesta de la Responses API con el JSON en output_text. */
    private function openai(array $json): array
    {
        return [
            'output_text' => json_encode($json),
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 200, 'total_tokens' => 1200],
        ];
    }

    public function test_from_pdf_extracts_and_returns_factura_without_real_openai(): void
    {
        Storage::fake('local');
        Event::fake([FacturaExtracted::class]);

        // total 121 = 100 + 21% → cuadra a la primera, sin fallback.
        Http::fake(['*/responses' => Http::response($this->openai($this->factura(121.0)), 200)]);

        $pdf = UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf');
        $factura = app(FacturaExtractor::class)->fromPdf($pdf, 7);

        $this->assertSame('F-1', $factura->numero);
        $this->assertTrue($factura->cuadra);
        $this->assertCount(1, $factura->albaranes);
        Http::assertSentCount(1);
        Event::assertDispatched(FacturaExtracted::class);
    }

    public function test_reprocesses_with_fallback_when_first_run_does_not_cuadrar(): void
    {
        Storage::fake('local');

        // 1ª (gpt-5.4-mini): total 999 no cuadra → 2ª (gpt-4.1): total 121 cuadra.
        Http::fakeSequence('*/responses')
            ->push($this->openai($this->factura(999.0)), 200)
            ->push($this->openai($this->factura(121.0)), 200);

        $pdf = UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf');
        $factura = app(FacturaExtractor::class)->fromPdf($pdf);

        $this->assertTrue($factura->cuadra);
        $this->assertEqualsWithDelta(121.0, (float) $factura->total, 0.001);
        Http::assertSentCount(2); // hubo reproceso con el modelo de respaldo
    }
}
