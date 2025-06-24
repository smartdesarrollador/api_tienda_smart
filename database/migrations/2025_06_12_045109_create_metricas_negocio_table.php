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
        Schema::create('metricas_negocio', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->integer('pedidos_totales')->default(0);
            $table->integer('pedidos_entregados')->default(0);
            $table->integer('pedidos_cancelados')->default(0);
            $table->decimal('ventas_totales', 12, 2)->default(0);
            $table->decimal('costo_envios', 10, 2)->default(0);
            $table->integer('nuevos_clientes')->default(0);
            $table->integer('clientes_recurrentes')->default(0);
            $table->decimal('tiempo_promedio_entrega', 8, 2)->default(0); // minutos
            $table->integer('productos_vendidos')->default(0);
            $table->decimal('ticket_promedio', 10, 2)->default(0);
            $table->json('productos_mas_vendidos')->nullable(); // top 10
            $table->json('zonas_mas_activas')->nullable(); // estadísticas por zona
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metricas_negocio');
    }
};
