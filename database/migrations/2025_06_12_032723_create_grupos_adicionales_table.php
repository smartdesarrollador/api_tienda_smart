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
        Schema::create('grupos_adicionales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ej: "Salsas", "Quesos", "Carnes Extra"
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('obligatorio')->default(false); // Si es obligatorio elegir del grupo
            $table->boolean('multiple_seleccion')->default(true); // Si permite múltiples selecciones
            $table->integer('minimo_selecciones')->default(0);
            $table->integer('maximo_selecciones')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos_adicionales');
    }
};
