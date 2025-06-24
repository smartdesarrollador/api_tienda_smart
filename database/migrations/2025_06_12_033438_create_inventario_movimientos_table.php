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
        Schema::create('inventario_movimientos', function (Blueprint $table) {
           $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('variacion_id')->nullable()->constrained('variaciones_productos');
            $table->enum('tipo_movimiento', ['entrada', 'salida', 'ajuste', 'reserva', 'liberacion']);
            $table->integer('cantidad'); // + entrada, - salida
            $table->integer('stock_anterior');
            $table->integer('stock_nuevo');
            $table->string('motivo'); // 'Venta', 'Compra', 'Merma', 'Ajuste manual'
            $table->string('referencia')->nullable(); // pedido_id, compra_id, etc.
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
    }
};
