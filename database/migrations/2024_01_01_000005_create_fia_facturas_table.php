<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('facturas-ia.table_prefix', 'fia_');

        Schema::create($prefix.'facturas', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('proveedor_id')->nullable()->constrained($prefix.'proveedores')->nullOnDelete();
            $table->foreignId('receptor_id')->nullable()->constrained($prefix.'receptores')->nullOnDelete();
            $table->foreignId('document_id')->constrained($prefix.'documents')->cascadeOnDelete();
            $table->foreignId('extraction_run_id')->nullable()->constrained($prefix.'extraction_runs')->nullOnDelete();
            $table->string('numero')->nullable();
            $table->date('fecha')->nullable();
            $table->decimal('total', 14, 4)->nullable();
            $table->decimal('portes', 14, 4)->nullable();
            $table->boolean('cuadra')->nullable();
            $table->boolean('revisada')->default(false);
            $table->boolean('duplicada')->default(false);
            $table->timestamps();

            $table->unique('document_id'); // una factura por documento
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('facturas-ia.table_prefix', 'fia_').'facturas');
    }
};
