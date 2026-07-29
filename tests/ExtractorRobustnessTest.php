<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Tests;

use Appsur\FacturasIa\Events\ExtractionFailed;
use Appsur\FacturasIa\Exceptions\ExtractionException;
use Appsur\FacturasIa\FacturaExtractor;
use Appsur\FacturasIa\Models\Document;
use Appsur\FacturasIa\Settings\FacturasIaSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ExtractorRobustnessTest extends TestCase
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

    private function ok(array $json): array
    {
        return ['output_text' => json_encode($json), 'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15]];
    }

    private function badJson(): array
    {
        return ['output_text' => 'esto no es json', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15]];
    }

    public function test_fallback_runs_when_first_model_fails(): void
    {
        Storage::fake('local');
        // 1º modelo devuelve JSON inválido (falla), el de respaldo devuelve una factura válida.
        Http::fakeSequence('*/responses')
            ->push($this->badJson(), 200)
            ->push($this->ok($this->factura(121.0)), 200);

        $pdf = UploadedFile::fake()->create('f.pdf', 10, 'application/pdf');
        $factura = app(FacturaExtractor::class)->fromPdf($pdf);

        $this->assertSame('F-1', $factura->numero);
        $this->assertTrue($factura->cuadra);
        Http::assertSentCount(2); // se intentó el modelo de respaldo tras el fallo
    }

    public function test_total_failure_dispatches_event_and_throws(): void
    {
        Storage::fake('local');
        Event::fake([ExtractionFailed::class]);
        Http::fake(['*/responses' => Http::response($this->badJson(), 200)]); // ambos modelos fallan

        $pdf = UploadedFile::fake()->create('f.pdf', 10, 'application/pdf');

        $this->expectException(ExtractionException::class);
        try {
            app(FacturaExtractor::class)->fromPdf($pdf);
        } finally {
            Event::assertDispatched(ExtractionFailed::class);
        }
    }

    public function test_missing_api_key_fails_fast_without_uploading(): void
    {
        Storage::fake('local');
        $s = app(FacturasIaSettings::class);
        $s->openaiKey = null;
        $s->save();
        config(['facturas-ia.openai.key' => null]);
        Http::fake();

        $pdf = UploadedFile::fake()->create('f.pdf', 10, 'application/pdf');

        try {
            app(FacturaExtractor::class)->fromPdf($pdf);
            $this->fail('Debería haber lanzado ExtractionException');
        } catch (ExtractionException $e) {
            // No debe haber creado ningún documento ni llamado a OpenAI.
            $this->assertSame(0, Document::count());
            Http::assertNothingSent();
        }
    }

    public function test_dedupe_skips_openai_for_identical_pdf(): void
    {
        Storage::fake('local');
        Http::fake(['*/responses' => Http::response($this->ok($this->factura(121.0)), 200)]);

        $path = sys_get_temp_dir().'/fia-dedupe-test.pdf';
        file_put_contents($path, '%PDF-1.4 dedupe-fixed-bytes');

        $extractor = app(FacturaExtractor::class);
        $f1 = $extractor->fromPdf($path);
        $f2 = $extractor->fromPdf($path); // idéntico → debe reutilizar sin llamar a OpenAI

        $this->assertSame($f1->id, $f2->id);
        $this->assertSame(1, Document::count());
        Http::assertSentCount(1);

        @unlink($path);
    }
}
