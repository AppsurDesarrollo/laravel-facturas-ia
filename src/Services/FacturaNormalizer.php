<?php

namespace Appsur\FacturasIa\Services;

use Appsur\FacturasIa\Models\Document;
use Appsur\FacturasIa\Models\ExtractionRun;
use Appsur\FacturasIa\Models\Factura;
use Appsur\FacturasIa\Models\Proveedor;
use Appsur\FacturasIa\Models\Receptor;
use Appsur\FacturasIa\Support\CuadreChecker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Normaliza el resultado de una extracción (result_json de un run) en las tablas de
 * dominio: proveedor, receptor, factura, albaranes e items.
 */
class FacturaNormalizer
{
    /**
     * Crea/reemplaza la factura normalizada del documento a partir del run indicado.
     *
     * @param  ?string  $tipo  'recibida' | 'emitida' para forzarlo; null autodetecta por NIF (config own_nifs).
     */
    public static function fromRun(Document $document, ExtractionRun $run, ?string $tipo = null): void
    {
        $json = $run->result_json;

        DB::transaction(function () use ($json, $document, $run, $tipo) {
            $proveedor = self::firstOrCreateParty(Proveedor::class, $json['proveedor'] ?? []);
            $receptor = self::firstOrCreateParty(Receptor::class, $json['receptor'] ?? []);

            // Una factura por documento: si ya existía, se reemplaza (cascade borra albaranes/items).
            Factura::where('document_id', $document->id)->get()->each->delete();

            $total = 0.0;
            $hasTotal = false;
            $extractedTotal = self::num($json['total'] ?? null);
            $numero = self::str($json['numero_factura'] ?? null);

            // Duplicada: ya existe otra factura con el mismo proveedor (NIF) y nº.
            // La factura previa de ESTE documento ya se borró arriba, así que no se auto-marca.
            $duplicada = $proveedor !== null && $numero !== null
                && Factura::where('proveedor_id', $proveedor->id)->where('numero', $numero)->exists();

            $factura = Factura::create([
                'proveedor_id' => $proveedor?->id,
                'receptor_id' => $receptor?->id,
                'document_id' => $document->id,
                'extraction_run_id' => $run->id,
                'numero' => $numero,
                'tipo' => self::resolveTipo($tipo, $proveedor, $receptor),
                'fecha' => self::date($json['fecha'] ?? null),
                'total' => $extractedTotal,
                'portes' => self::num($json['portes'] ?? null),
                'cuadra' => CuadreChecker::cuadra($json),
                'duplicada' => $duplicada,
            ]);

            foreach ($json['albaranes'] ?? [] as $alb) {
                $albaran = $factura->albaranes()->create([
                    'numero' => self::str($alb['numero_albaran'] ?? null),
                    'fecha' => self::date($alb['fecha_albaran'] ?? null),
                ]);

                foreach ($alb['items'] ?? [] as $it) {
                    $importe = self::num($it['importe'] ?? null);
                    if ($importe !== null) {
                        $total += $importe;
                        $hasTotal = true;
                    }

                    $albaran->items()->create([
                        'concepto' => self::str($it['concepto'] ?? null),
                        'cantidad' => self::num($it['cantidad'] ?? null),
                        'iva' => self::num($it['iva'] ?? null),
                        'precio' => self::num($it['precio'] ?? null),
                        'importe' => $importe,
                        'descuento' => self::num($it['descuento'] ?? null),
                    ]);
                }
            }

            // Si la factura no traía un total explícito, usa la suma de importes.
            if ($extractedTotal === null && $hasTotal) {
                $factura->update(['total' => round($total, 4)]);
            }
        });
    }

    /** Dedupe por NIF cuando existe; si el bloque está vacío devuelve null. */
    private static function firstOrCreateParty(string $class, array $data): ?object
    {
        $attrs = [
            'nif' => self::str($data['nif'] ?? null),
            'nombre' => self::str($data['nombre'] ?? null),
            'direccion' => self::str($data['direccion'] ?? null),
            'cp' => self::str($data['cp'] ?? null),
            'localidad' => self::str($data['localidad'] ?? null),
            'provincia' => self::str($data['provincia'] ?? null),
        ];

        if (count(array_filter($attrs, fn ($v) => $v !== null && $v !== '')) === 0) {
            return null;
        }

        if (! empty($attrs['nif'])) {
            return $class::firstOrCreate(['nif' => $attrs['nif']], $attrs);
        }

        return $class::create($attrs);
    }

    /**
     * Dirección de la factura. Explícito manda; si no, autodetecta comparando tu NIF
     * (config own_nifs) con emisor/receptor: tu NIF emisor → emitida; receptor → recibida.
     */
    private static function resolveTipo(?string $explicit, ?object $proveedor, ?object $receptor): ?string
    {
        if ($explicit !== null) {
            return $explicit;
        }

        $own = array_filter(array_map([self::class, 'normalizeNif'], (array) config('facturas-ia.own_nifs', [])));
        if (empty($own)) {
            return null;
        }

        $prov = self::normalizeNif($proveedor->nif ?? null);
        $rec = self::normalizeNif($receptor->nif ?? null);

        if ($prov !== null && in_array($prov, $own, true)) {
            return Factura::TIPO_EMITIDA;   // tu empresa emite → venta
        }
        if ($rec !== null && in_array($rec, $own, true)) {
            return Factura::TIPO_RECIBIDA;  // tu empresa recibe → compra
        }

        return null;
    }

    /** Normaliza un NIF para comparar: sin espacios/guiones, mayúsculas y sin prefijo de país (ES). */
    private static function normalizeNif(?string $nif): ?string
    {
        if ($nif === null) {
            return null;
        }
        $n = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $nif));
        // Quita un prefijo de país de 2 letras (ES...) si deja un NIF de longitud normal.
        if (strlen($n) > 9 && preg_match('/^[A-Z]{2}[0-9A-Z]/', $n)) {
            $n = substr($n, 2);
        }

        return $n !== '' ? $n : null;
    }

    private static function str($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function num($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        // "1.234,56" o "1,50" → normaliza separadores.
        $clean = str_replace(['.', ' '], '', (string) $value);
        $clean = str_replace(',', '.', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    private static function date($value): ?string
    {
        $value = self::str($value);
        if ($value === null) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable $e) {
            return null;
        }
    }
}
