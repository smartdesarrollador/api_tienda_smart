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
        Schema::create('producto_grupo_adicional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->foreignId('grupo_adicional_id')->constrained('grupos_adicionales')->onDelete('cascade');
            $table->boolean('obligatorio')->default(false); // Si este grupo es obligatorio para el producto
            $table->integer('minimo_selecciones')->nullable(); // Override del grupo
            $table->integer('maximo_selecciones')->nullable(); // Override del grupo
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
        Schema::dropIfExists('producto_grupo_adicional');
    }
};
