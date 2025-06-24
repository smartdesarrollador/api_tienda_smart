<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variacion_valor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variacion_id')->constrained('variaciones_productos')->onDelete('cascade');
            $table->foreignId('valor_atributo_id')->constrained('valores_atributo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variacion_valor');
    }
}; 