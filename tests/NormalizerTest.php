<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Tests;

use Appsur\FacturasIa\Models\Document;
use Appsur\FacturasIa\Models\ExtractionRun;
use Appsur\FacturasIa\Services\FacturaNormalizer;

class NormalizerTest extends TestCase
{
    private function sample(string $numero = 'F-1'): array
    {
        return [
            'proveedor' => ['nif' => 'ES-B12345678', 'nombre' => 'Proveedor SL'],
            'receptor' => ['nif' => 'ES-B99999999', 'nombre' => 'Receptor SL'],
            'numero_factura' => $numero,
            'fecha' => '2026-06-30',
            'portes' => null,
            'total' => 121.0, // 100 base + 21% IVA
            'albaranes' => [[
                'numero_albaran' => 'ALB-1',
                'fecha_albaran' => '2026-06-01',
                'items' => [
                    ['concepto' => 'Cable', 'cantidad' => 1, 'iva' => 21, 'precio' => 50, 'importe' => 50],
                    ['concepto' => 'Terminal', 'cantidad' => 2, 'iva' => 21, 'precio' => 25, 'importe' => 50],
                ],
            ]],
        ];
    }

    private function makeRun(array $json): array
    {
        $doc = Document::create([
            'original_name' => 'f.pdf', 'disk' => 'local', 'path' => 'x.pdf', 'size' => 1, 'mime' => 'application/pdf',
        ]);
        $run = ExtractionRun::create([
            'document_id' => $doc->id, 'model' => 'gpt-5.4-mini', 'status' => 'succeeded', 'result_json' => $json,
        ]);

        return [$doc, $run];
    }

    public function test_normalizes_into_domain_tables_and_computes_cuadra(): void
    {
        [$doc, $run] = $this->makeRun($this->sample());

        FacturaNormalizer::fromRun($doc, $run);

        $factura = $doc->refresh()->factura;
        $this->assertNotNull($factura);
        $this->assertSame('F-1', $factura->numero);
        $this->assertEqualsWithDelta(121.0, (float) $factura->total, 0.001);
        $this->assertTrue($factura->cuadra);
        $this->assertFalse($factura->duplicada);
        $this->assertSame('ES-B12345678', $factura->proveedor->nif);
        $this->assertCount(1, $factura->albaranes);
        $this->assertCount(2, $factura->albaranes->first()->items);
    }

    public function test_marks_duplicada_on_same_proveedor_and_numero(): void
    {
        [$doc1, $run1] = $this->makeRun($this->sample('DUP-1'));
        FacturaNormalizer::fromRun($doc1, $run1);

        [$doc2, $run2] = $this->makeRun($this->sample('DUP-1'));
        FacturaNormalizer::fromRun($doc2, $run2);

        $this->assertTrue($doc2->refresh()->factura->duplicada);
    }

    public function test_tipo_can_be_forced(): void
    {
        [$doc, $run] = $this->makeRun($this->sample('T-1'));
        FacturaNormalizer::fromRun($doc, $run, 'emitida');

        $this->assertSame('emitida', $doc->refresh()->factura->tipo);
    }

    public function test_tipo_autodetected_from_own_nifs(): void
    {
        // Mi NIF es el RECEPTOR → factura recibida (compra).
        config(['facturas-ia.own_nifs' => ['B99999999']]); // sin prefijo ES, debe casar igual
        [$doc, $run] = $this->makeRun($this->sample('R-1'));
        FacturaNormalizer::fromRun($doc, $run);
        $this->assertSame('recibida', $doc->refresh()->factura->tipo);

        // Mi NIF es el EMISOR → factura emitida (venta).
        config(['facturas-ia.own_nifs' => ['ES-B12345678']]);
        [$doc2, $run2] = $this->makeRun($this->sample('E-1'));
        FacturaNormalizer::fromRun($doc2, $run2);
        $this->assertSame('emitida', $doc2->refresh()->factura->tipo);
    }
}
