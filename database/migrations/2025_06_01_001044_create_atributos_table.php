<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atributos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ej: Color, Tamaño, Memoria RAM
            $table->string('slug')->unique();
            $table->string('tipo')->default('texto'); // texto, color, numero, etc
            $table->boolean('filtrable')->default(true);
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atributos');
    }
}; 