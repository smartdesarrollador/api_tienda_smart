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
        Schema::create('zona_distrito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_reparto_id')->constrained('zonas_reparto')->onDelete('cascade');
            $table->foreignId('distrito_id')->constrained('distritos')->onDelete('cascade');
            $table->decimal('costo_envio_personalizado', 8, 2)->nullable(); // Override del costo de zona
            $table->integer('tiempo_adicional')->default(0); // Minutos adicionales para este distrito
            $table->boolean('activo')->default(true);
            $table->integer('prioridad')->default(1); // 1=alta, 2=media, 3=baja prioridad
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zona_distrito');
    }
};
