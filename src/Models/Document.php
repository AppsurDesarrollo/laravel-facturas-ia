<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Models;

use Appsur\FacturasIa\Models\Concerns\PrefixedTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $original_name
 * @property string $disk
 * @property string $path
 * @property int $size
 * @property string $mime
 * @property string|null $hash
 * @property-read Factura|null $factura
 * @property-read Collection<int, ExtractionRun> $runs
 */
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

    /** @return HasMany<ExtractionRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }

    /** @return HasOne<Factura, $this> */
    public function factura(): HasOne
    {
        return $this->hasOne(Factura::class);
    }
}
