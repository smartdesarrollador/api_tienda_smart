<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filtro_valor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filtro_id')->constrained('filtros_avanzados')->onDelete('cascade');
            $table->string('valor');
            $table->string('codigo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filtro_valor');
    }
}; 