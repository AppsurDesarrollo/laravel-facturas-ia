<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('facturas-ia.table_prefix', 'fia_');

        Schema::create($prefix.'proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nif')->nullable()->index();
            $table->string('nombre')->nullable();
            $table->string('direccion')->nullable();
            $table->string('cp')->nullable();
            $table->string('localidad')->nullable();
            $table->string('provincia')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('facturas-ia.table_prefix', 'fia_').'proveedores');
    }
};
