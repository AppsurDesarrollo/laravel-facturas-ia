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
 * @property int $factura_id
 * @property string|null $numero
 * @property Carbon|null $fecha
 * @property-read Factura|null $factura
 * @property-read Collection<int, Item> $items
 */
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
