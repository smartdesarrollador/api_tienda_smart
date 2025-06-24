<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imagenes_productos', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('alt')->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('principal')->default(false);
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->unsignedBigInteger('variacion_id')->nullable();
            $table->string('tipo')->nullable(); // miniatura, galeria, zoom, etc
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagenes_productos');
    }
}; 