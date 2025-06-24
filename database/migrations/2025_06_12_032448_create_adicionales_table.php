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
        Schema::create('adicionales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ej: Salsa BBQ, Queso Cheddar, Tocino
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 10, 2)->default(0); // Precio adicional
            $table->string('imagen')->nullable();
            $table->string('icono')->nullable(); // Icono para mostrar en la UI
            $table->enum('tipo', ['salsa', 'queso', 'carne', 'vegetal', 'condimento', 'otro'])->default('otro');
            $table->boolean('disponible')->default(true);
            $table->boolean('activo')->default(true);
            $table->integer('stock')->nullable(); // Null = sin límite de stock
            $table->integer('tiempo_preparacion')->nullable(); // Tiempo adicional en minutos
            $table->decimal('calorias', 8, 2)->nullable(); // Información nutricional
            $table->json('informacion_nutricional')->nullable(); // Datos adicionales de nutrición
            $table->json('alergenos')->nullable(); // ['gluten', 'lactosa', 'frutos_secos']
            $table->boolean('vegetariano')->default(false);
            $table->boolean('vegano')->default(false);
            $table->integer('orden')->default(0); // Para ordenar en la presentación
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adicionales');
    }
};
