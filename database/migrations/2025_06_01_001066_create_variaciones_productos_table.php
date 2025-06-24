<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variaciones_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->string('sku')->unique();
            $table->decimal('precio', 12, 2);
            $table->decimal('precio_oferta', 12, 2)->nullable();
            $table->integer('stock');
            $table->boolean('activo')->default(true);
            $table->json('atributos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variaciones_productos');
    }
}; 