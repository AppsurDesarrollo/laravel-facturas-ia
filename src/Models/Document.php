<?php

namespace Appsur\FacturasIa\Models;

use Appsur\FacturasIa\Models\Concerns\PrefixedTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    use PrefixedTable;

    protected string $baseTable = 'documents';

    protected $fillable = [
        'user_id',
        'original_name',
        'disk',
        'path',
        'size',
        'mime',
        'hash',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }

    public function factura(): HasOne
    {
        return $this->hasOne(Factura::class);
    }
}
