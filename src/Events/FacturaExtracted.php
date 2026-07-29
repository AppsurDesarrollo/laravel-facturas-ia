<?php

namespace Appsur\FacturasIa\Events;

use Appsur\FacturasIa\Models\ExtractionRun;
use Appsur\FacturasIa\Models\Factura;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara cuando una factura se extrae y normaliza con éxito. El proyecto host puede
 * escucharlo para lo suyo (por ejemplo, enviar el JSON a su API). El paquete no envía nada.
 */
class FacturaExtracted
{
    use Dispatchable;

    public function __construct(
        public Factura $factura,
        public ExtractionRun $run,
    ) {}
}
