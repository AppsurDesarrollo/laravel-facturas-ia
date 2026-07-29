<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Tests;

use Appsur\FacturasIa\Models\Document;
use Appsur\FacturasIa\Services\DocumentUploadService;
use Appsur\FacturasIa\Services\OpenAiUsageService;
use Appsur\FacturasIa\Support\CuadreChecker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ServicesTest extends TestCase
{
    // --- DocumentUploadService ---

    public function test_upload_stores_pdf_with_hash_and_deletes(): void
    {
        Storage::fake('local');
        $service = app(DocumentUploadService::class);

        $pdf = UploadedFile::fake()->create('factura.pdf', 12, 'application/pdf');
        $document = $service->store($pdf, 9);

        $this->assertSame('factura.pdf', $document->original_name);
        $this->assertSame(9, $document->user_id);
        $this->assertSame(64, strlen($document->hash));
        Storage::disk('local')->assertExists($document->path);

        $service->delete($document);
        Storage::disk('local')->assertMissing($document->path);
        $this->assertSame(0, Document::count());
    }

    public function test_upload_from_string_path_and_hash_is_stable(): void
    {
        Storage::fake('local');
        $path = sys_get_temp_dir().'/fia-upload-test.pdf';
        file_put_contents($path, '%PDF-1.4 contenido');

        $document = app(DocumentUploadService::class)->store($path, null);
        $this->assertSame('fia-upload-test.pdf', $document->original_name);
        $this->assertSame(DocumentUploadService::hashFor($path), $document->hash);

        @unlink($path);
    }

    // --- OpenAiUsageService ---

    public function test_usage_service_reports_no_admin_key(): void
    {
        config(['facturas-ia.openai.admin_key' => null]);
        $usage = app(OpenAiUsageService::class);

        $this->assertFalse($usage->hasAdminKey());
        $this->assertSame('no_admin_key', $usage->costs(0)['error'] ?? null);
    }

    public function test_usage_service_reads_costs_with_admin_key(): void
    {
        config(['facturas-ia.openai.admin_key' => 'sk-admin-test']);
        Http::fake(['*/organization/costs*' => Http::response(['data' => [['results' => []]]], 200)]);

        $out = app(OpenAiUsageService::class)->costs(0);
        $this->assertArrayHasKey('data', $out);
        $this->assertArrayNotHasKey('error', $out);
    }

    // --- CuadreChecker ---

    public function test_cuadre_cases(): void
    {
        // Cuadra con IVA por línea (100 + 21%).
        $this->assertTrue(CuadreChecker::cuadra($this->doc(121.0, [[100, 21]])));
        // Cuadra con portes sin IVA (100 líneas + 10 portes).
        $this->assertTrue(CuadreChecker::cuadra($this->doc(110.0, [[100, 0]], 10.0)));
        // No cuadra (total disparatado).
        $this->assertFalse(CuadreChecker::cuadra($this->doc(500.0, [[100, 21]])));
        // Sin total → no comprobable → true (no reprocesar).
        $this->assertTrue(CuadreChecker::cuadra($this->doc(null, [[100, 21]])));
        // Abono (total negativo) que cuadra.
        $this->assertTrue(CuadreChecker::cuadra($this->doc(-53.70, [[-53.70, 0]])));
    }

    /** @param  array<int, array{0: float, 1: float}>  $lines  [importe, iva] */
    private function doc(?float $total, array $lines, ?float $portes = null): array
    {
        return [
            'total' => $total,
            'portes' => $portes,
            'albaranes' => [[
                'items' => array_map(fn ($l) => ['importe' => $l[0], 'iva' => $l[1]], $lines),
            ]],
        ];
    }
}
