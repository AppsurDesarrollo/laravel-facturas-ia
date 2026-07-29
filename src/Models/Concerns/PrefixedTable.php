<?php

namespace Appsur\FacturasIa\Models\Concerns;

/**
 * Prefija el nombre de tabla con config('facturas-ia.table_prefix') para no chocar
 * con las tablas del proyecto host. Cada modelo define su $baseTable.
 */
trait PrefixedTable
{
    public function getTable(): string
    {
        return config('facturas-ia.table_prefix', 'fia_').$this->baseTable;
    }
}
