<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filtros_avanzados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ej: Banda de Red, Capacidad de Disco
            $table->string('slug')->unique();
            $table->string('tipo')->default('texto');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filtros_avanzados');
    }
}; 