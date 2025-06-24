<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valores_atributo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atributo_id')->constrained('atributos')->onDelete('cascade');
            $table->string('valor'); // Ej: Negro, 8GB, 256GB
            $table->string('codigo')->nullable(); // Ej: HEX para color
            $table->string('imagen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valores_atributo');
    }
}; 