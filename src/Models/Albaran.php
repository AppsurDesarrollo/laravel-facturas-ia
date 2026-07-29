<?php

namespace Appsur\FacturasIa\Models;

use Appsur\FacturasIa\Models\Concerns\PrefixedTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Albaran extends Model
{
    use PrefixedTable;

    protected string $baseTable = 'albaranes';

    protected $fillable = [
        'factura_id',
        'numero',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
