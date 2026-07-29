<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Models;

use Appsur\FacturasIa\Models\Concerns\PrefixedTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $albaran_id
 * @property string|null $concepto
 * @property string|null $cantidad
 * @property string|null $iva
 * @property string|null $precio
 * @property string|null $importe
 */
class Item extends Model
{
    use PrefixedTable;

    protected string $baseTable = 'items';

    protected $fillable = [
        'albaran_id',
        'concepto',
        'cantidad',
        'iva',
        'precio',
        'importe',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'iva' => 'decimal:2',
            'precio' => 'decimal:4',
            'importe' => 'decimal:4',
        ];
    }

    public function albaran(): BelongsTo
    {
        return $this->belongsTo(Albaran::class);
    }
}
