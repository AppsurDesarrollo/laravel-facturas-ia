<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('facturas-ia.table_prefix', 'fia_');

        Schema::create($prefix.'albaranes', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('factura_id')->constrained($prefix.'facturas')->cascadeOnDelete();
            $table->string('numero')->nullable();
            $table->date('fecha')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('facturas-ia.table_prefix', 'fia_').'albaranes');
    }
};
