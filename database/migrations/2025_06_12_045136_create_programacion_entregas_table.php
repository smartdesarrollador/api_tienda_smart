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
        Schema::create('programacion_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos');
            $table->foreignId('repartidor_id')->constrained('users');
            $table->datetime('fecha_programada');
            $table->time('hora_inicio_ventana'); // Ej: 12:00
            $table->time('hora_fin_ventana'); // Ej: 14:00
            $table->enum('estado', ['programado', 'en_ruta', 'entregado', 'fallido', 'reprogramado']);
            $table->integer('orden_ruta')->nullable(); // Orden en la ruta del repartidor
            $table->text('notas_repartidor')->nullable();
            $table->datetime('hora_salida')->nullable();
            $table->datetime('hora_llegada')->nullable();
            $table->string('motivo_fallo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programacion_entregas');
    }
};
