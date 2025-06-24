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
        Schema::create('carrito_temporal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('session_id')->nullable(); // Para usuarios no autenticados
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variacion_id')->nullable()->constrained('variaciones_productos');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2); // Precio al momento de agregar
            $table->json('adicionales_seleccionados')->nullable(); // {adicional_id: cantidad}
            $table->text('observaciones')->nullable();
            $table->datetime('fecha_expiracion')->nullable(); // Auto-limpiar carritos viejos
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carrito_temporal');
    }
};
