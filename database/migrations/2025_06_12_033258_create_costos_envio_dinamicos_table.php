<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('costos_envio_dinamicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_reparto_id')->constrained('zonas_reparto')->onDelete('cascade');
            $table->decimal('distancia_desde_km', 8, 2)->default(0); // Desde X km
            $table->decimal('distancia_hasta_km', 8, 2); // Hasta X km
            $table->decimal('costo_envio', 8, 2); // Costo para ese rango
            $table->decimal('tiempo_adicional', 8, 2)->default(0); // Minutos adicionales
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costos_envio_dinamicos');
    }
};
