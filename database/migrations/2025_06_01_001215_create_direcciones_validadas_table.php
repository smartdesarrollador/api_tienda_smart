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
        Schema::create('direcciones_validadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direccion_id')->constrained('direcciones')->onDelete('cascade');
            $table->foreignId('zona_reparto_id')->nullable()->constrained('zonas_reparto')->onDelete('set null');
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->decimal('distancia_tienda_km', 8, 2)->nullable(); // Distancia desde la tienda
            $table->boolean('en_zona_cobertura')->default(false);
            $table->decimal('costo_envio_calculado', 8, 2)->nullable();
            $table->integer('tiempo_entrega_estimado')->nullable(); // En minutos
            $table->datetime('fecha_ultima_validacion')->nullable();
            $table->text('observaciones_validacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direcciones_validadas');
    }
};
