<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Support;

/**
 * Comprueba si la suma de importes de las líneas cuadra con el total de la factura,
 * probando los IVA configurados sobre la base (o el IVA por línea). No depende de que
 * el modelo extraiga bien el campo IVA.
 */
class CuadreChecker
{
    /**
     * ¿Cuadra? Devuelve true también cuando NO se puede comprobar (sin total o sin
     * importes) para no reprocesar casos que el reproceso no arreglaría (abonos, etc.).
     */
    public static function cuadra(?array $json): bool
    {
        if (! $json || ! is_numeric($json['total'] ?? null)) {
            return true;
        }

        $total = (float) $json['total'];
        $base = 0.0;
        $withLineIva = 0.0;
        $has = false;

        foreach (($json['albaranes'] ?? []) as $albaran) {
            foreach (($albaran['items'] ?? []) as $item) {
                if (isset($item['importe']) && is_numeric($item['importe'])) {
                    $has = true;
                    $imp = (float) $item['importe'];
                    $base += $imp;
                    $iva = is_numeric($item['iva'] ?? null) ? (float) $item['iva'] : 0.0;
                    $withLineIva += $imp * (1 + $iva / 100);
                }
            }
        }

        if (! $has) {
            return true;
        }

        $portes = is_numeric($json['portes'] ?? null) ? (float) $json['portes'] : 0.0;
        $tolAbs = (float) config('facturas-ia.cuadre.tolerance_abs', 0.5);
        $tolPct = (float) config('facturas-ia.cuadre.tolerance_pct', 0.01);
        $tol = max($tolAbs, abs($total) * $tolPct);

        $candidates = [$withLineIva, $withLineIva + $portes];
        foreach ((array) config('facturas-ia.cuadre.iva_rates', [0, 4, 10, 21]) as $pct) {
            $rate = 1 + ((float) $pct) / 100;
            $candidates[] = $base * $rate;                 // solo líneas
            $candidates[] = ($base + $portes) * $rate;     // líneas + portes, mismo IVA
            $candidates[] = $base * $rate + $portes;       // líneas con IVA + portes sin IVA
        }

        foreach ($candidates as $candidate) {
            if (abs($candidate - $total) <= $tol) {
                return true;
            }
        }

        return false;
    }
}
