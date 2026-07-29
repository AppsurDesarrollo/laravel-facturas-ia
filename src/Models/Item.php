<?php

namespace Appsur\FacturasIa\Models;

use Appsur\FacturasIa\Models\Concerns\PrefixedTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'descuento',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'iva' => 'decimal:2',
            'precio' => 'decimal:4',
            'importe' => 'decimal:4',
            'descuento' => 'decimal:4',
        ];
    }

    public function albaran(): BelongsTo
    {
        return $this->belongsTo(Albaran::class);
    }
}
