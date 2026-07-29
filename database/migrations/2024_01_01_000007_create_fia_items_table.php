<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('facturas-ia.table_prefix', 'fia_');

        Schema::create($prefix.'items', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('albaran_id')->constrained($prefix.'albaranes')->cascadeOnDelete();
            $table->string('concepto')->nullable();
            $table->decimal('cantidad', 12, 3)->nullable();
            $table->decimal('iva', 5, 2)->nullable();     // porcentaje
            $table->decimal('precio', 12, 4)->nullable(); // unitario
            $table->decimal('importe', 14, 4)->nullable(); // subtotal de la línea
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('facturas-ia.table_prefix', 'fia_').'items');
    }
};
