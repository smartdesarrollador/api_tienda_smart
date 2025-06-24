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
        Schema::create('distritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provincia_id')->constrained('provincias')->onDelete('cascade');
            $table->string('nombre'); // Miraflores, San Borja, Lince
            $table->string('codigo', 15)->unique(); // LIM0101, LIM0102
            $table->string('codigo_inei', 15)->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->decimal('latitud', 10, 8)->nullable(); // Coordenada central del distrito
            $table->decimal('longitud', 11, 8)->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('disponible_delivery')->default(false); // Si hacemos delivery aquí
            $table->json('limites_geograficos')->nullable(); // Polígono del distrito
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distritos');
    }
};
