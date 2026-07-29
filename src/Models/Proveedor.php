<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Models;

use Appsur\FacturasIa\Models\Concerns\PrefixedTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $nif
 * @property string|null $nombre
 * @property string|null $direccion
 * @property string|null $cp
 * @property string|null $localidad
 * @property string|null $provincia
 */
class Proveedor extends Model
{
    use PrefixedTable;

    protected string $baseTable = 'proveedores';

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
