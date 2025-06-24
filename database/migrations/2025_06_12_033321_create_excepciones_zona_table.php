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
        Schema::create('excepciones_zona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_reparto_id')->constrained('zonas_reparto')->onDelete('cascade');
            $table->date('fecha_excepcion');
            $table->enum('tipo', ['no_disponible', 'horario_especial', 'costo_especial', 'tiempo_especial']);
            $table->time('hora_inicio')->nullable(); // Para horarios especiales
            $table->time('hora_fin')->nullable();
            $table->decimal('costo_especial', 8, 2)->nullable(); // Para costos especiales
            $table->integer('tiempo_especial_min')->nullable(); // Tiempo especial mínimo
            $table->integer('tiempo_especial_max')->nullable(); // Tiempo especial máximo
            $table->text('motivo'); // Razón de la excepción
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excepciones_zona');
    }
};
