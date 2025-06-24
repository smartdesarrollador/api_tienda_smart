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
        Schema::create('seguimiento_pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->onDelete('cascade');
            $table->enum('estado_anterior', ['pendiente', 'confirmado', 'preparando', 'listo', 'enviado', 'entregado', 'cancelado', 'devuelto'])->nullable();
            $table->enum('estado_actual', ['pendiente', 'confirmado', 'preparando', 'listo', 'enviado', 'entregado', 'cancelado', 'devuelto']);
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_cambio_id')->nullable()->constrained('users'); // Quien hizo el cambio
            $table->decimal('latitud_seguimiento', 10, 8)->nullable(); // Para tracking GPS del repartidor
            $table->decimal('longitud_seguimiento', 11, 8)->nullable();
            $table->integer('tiempo_estimado_restante')->nullable(); // Minutos restantes
            $table->datetime('fecha_cambio');
            $table->boolean('notificado_cliente')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seguimiento_pedidos');
    }
};
