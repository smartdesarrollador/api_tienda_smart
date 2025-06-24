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
        Schema::create('producto_adicional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->foreignId('adicional_id')->constrained('adicionales')->onDelete('cascade');
            $table->boolean('obligatorio')->default(false); // Si es obligatorio seleccionar este adicional
            $table->boolean('multiple')->default(true); // Si se puede seleccionar múltiples veces
            $table->integer('cantidad_minima')->default(0); // Cantidad mínima a seleccionar
            $table->integer('cantidad_maxima')->nullable(); // Cantidad máxima a seleccionar (null = sin límite)
            $table->decimal('precio_personalizado', 10, 2)->nullable(); // Precio específico para este producto
            $table->boolean('incluido_gratis')->default(false); // Si viene incluido sin costo
            $table->integer('orden')->default(0); // Orden de presentación para este producto
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_adicional');
    }
};
