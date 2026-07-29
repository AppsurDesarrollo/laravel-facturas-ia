<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dirección de la factura: 'recibida' (compra, la recibes de un proveedor) o
 * 'emitida' (venta, la emites tú a un cliente). Se fija explícitamente al procesar
 * o se autodetecta comparando tu NIF (config own_nifs) con emisor/receptor.
 */
return new class extends Migration
{
    public function up(): void
    {
        $facturas = config('facturas-ia.table_prefix', 'fia_').'facturas';

        Schema::table($facturas, function (Blueprint $table) {
            $table->string('tipo')->nullable()->index()->after('numero'); // recibida | emitida | null
        });
    }

    public function down(): void
    {
        $facturas = config('facturas-ia.table_prefix', 'fia_').'facturas';

        Schema::table($facturas, function (Blueprint $table) {
            $table->dropIndex(['tipo']);
            $table->dropColumn('tipo');
        });
    }
};
