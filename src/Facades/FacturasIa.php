<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Facades;

use Appsur\FacturasIa\FacturaExtractor;
use Appsur\FacturasIa\Models\Factura;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Factura fromPdf(\Illuminate\Http\UploadedFile|string $pdf, ?int $userId = null, ?string $tipo = null)
 * @method static \Appsur\FacturasIa\Models\Document queueFromPdf(\Illuminate\Http\UploadedFile|string $pdf, ?int $userId = null, ?string $tipo = null)
 * @method static Factura extractDocument(\Appsur\FacturasIa\Models\Document $document, ?int $userId = null, ?string $tipo = null)
 *
 * @see FacturaExtractor
 */
class FacturasIa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'facturas-ia';
    }
}
