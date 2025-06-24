<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('distrito_id')->constrained('distritos');
            $table->string('direccion');
            $table->string('referencia')->nullable();
            
            $table->string('codigo_postal')->nullable();
             $table->string('numero_exterior', 20)->nullable();
            $table->string('numero_interior', 20)->nullable();
            $table->string('urbanizacion')->nullable();
            $table->string('etapa')->nullable();
            $table->string('manzana', 10)->nullable();
            $table->string('lote', 10)->nullable();
            $table->decimal('latitud', 10, 8)->nullable(); // GPS específico de la dirección
            $table->decimal('longitud', 11, 8)->nullable();
            $table->boolean('predeterminada')->default(false);
            $table->boolean('validada')->default(false); // Si fue validada geográficamente
            $table->string('alias')->nullable(); // "Casa", "Trabajo", "Casa de mamá"
            $table->text('instrucciones_entrega')->nullable(); // Para repartidores
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direcciones');
    }
}; 