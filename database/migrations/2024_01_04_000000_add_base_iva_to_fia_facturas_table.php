<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade a la factura la base imponible (total sin IVA) y la cuota de IVA,
 * tal como aparecen desglosadas en la propia factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('facturas-ia.table_prefix', 'fia_');

        Schema::table($prefix.'facturas', function (Blueprint $table) {
            $table->decimal('base_imponible', 14, 4)->nullable()->after('fecha');
            $table->decimal('cuota_iva', 14, 4)->nullable()->after('base_imponible');
        });
    }

    public function down(): void
    {
        $prefix = config('facturas-ia.table_prefix', 'fia_');

        Schema::table($prefix.'facturas', function (Blueprint $table) {
            $table->dropColumn(['base_imponible', 'cuota_iva']);
        });
    }
};
