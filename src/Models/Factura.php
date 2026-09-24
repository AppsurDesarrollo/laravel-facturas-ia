<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Models;

use Appsur\FacturasIa\Models\Concerns\PrefixedTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $proveedor_id
 * @property int|null $receptor_id
 * @property int $document_id
 * @property int|null $extraction_run_id
 * @property string|null $numero
 * @property string|null $tipo
 * @property Carbon|null $fecha
 * @property string|null $total
 * @property string|null $portes
 * @property bool|null $cuadra
 * @property bool $revisada
 * @property bool $duplicada
 * @property-read Proveedor|null $proveedor
 * @property-read Receptor|null $receptor
 * @property-read Document|null $document
 * @property-read Collection<int, Albaran> $albaranes
 */
class Factura extends Model
{
    use PrefixedTable;

    /** Dirección de la factura. */
    public const TIPO_RECIBIDA = 'recibida'; // compra: la recibes de un proveedor

    public const TIPO_EMITIDA = 'emitida';   // venta: la emites tú a un cliente

    protected string $baseTable = 'facturas';

    protected $fillable = [
        'proveedor_id',
        'receptor_id',
        'document_id',
        'extraction_run_id',
        'numero',
        'tipo',
        'fecha',
        'base_imponible',
        'cuota_iva',
        'total',
        'portes',
        'cuadra',
        'revisada',
        'duplicada',
    ];

    public function scopeRecibidas($query)
    {
        return $query->where('tipo', self::TIPO_RECIBIDA);
    }

    public function scopeEmitidas($query)
    {
        return $query->where('tipo', self::TIPO_EMITIDA);
    }

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'base_imponible' => 'decimal:4',
            'cuota_iva' => 'decimal:4',
            'total' => 'decimal:4',
            'portes' => 'decimal:4',
            'cuadra' => 'boolean',
            'revisada' => 'boolean',
            'duplicada' => 'boolean',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(Receptor::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function extractionRun(): BelongsTo
    {
        return $this->belongsTo(ExtractionRun::class);
    }

    public function albaranes(): HasMany
    {
        return $this->hasMany(Albaran::class);
    }
}
