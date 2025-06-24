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
        Schema::create('zonas_reparto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ej: Zona Centro, Zona Sur, Zona Norte
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->decimal('costo_envio', 8, 2)->default(0); // Costo base de envío
            $table->decimal('costo_envio_adicional', 8, 2)->default(0); // Costo adicional por distancia
            $table->integer('tiempo_entrega_min')->default(30); // Tiempo mínimo en minutos
            $table->integer('tiempo_entrega_max')->default(60); // Tiempo máximo en minutos
            $table->decimal('pedido_minimo', 10, 2)->nullable(); // Monto mínimo de pedido
            $table->decimal('radio_cobertura_km', 8, 2)->nullable(); // Radio de cobertura en km
            $table->string('coordenadas_centro', 100)->nullable(); // "lat,lng" del centro de la zona
            $table->json('poligono_cobertura')->nullable(); // Coordenadas del polígono de cobertura
            $table->boolean('activo')->default(true);
            $table->boolean('disponible_24h')->default(false);
            $table->integer('orden')->default(0); // Para ordenar en listas
            $table->string('color_mapa', 7)->nullable(); // Color hexadecimal para mostrar en mapas
            $table->text('observaciones')->nullable(); // Notas especiales de la zona
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zonas_reparto');
    }
};
