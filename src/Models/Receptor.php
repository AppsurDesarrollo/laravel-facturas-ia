<?php

namespace Appsur\FacturasIa\Models;

use Appsur\FacturasIa\Models\Concerns\PrefixedTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receptor extends Model
{
    use PrefixedTable;

    protected string $baseTable = 'receptores';

    protected $fillable = [
        'nif',
        'nombre',
        'direccion',
        'cp',
        'localidad',
        'provincia',
    ];

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }
}
