<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->text('descripcion');
            $table->decimal('precio', 12, 2);
            $table->decimal('precio_oferta', 12, 2)->nullable();
            $table->integer('stock');
            $table->string('sku')->unique();
            $table->string('codigo_barras')->nullable();
            $table->string('imagen_principal');
            $table->boolean('destacado')->default(false);
            $table->boolean('activo')->default(true);
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('garantia')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('idioma', 5)->default('es');
            $table->string('moneda', 5)->default('PEN');
            $table->json('atributos_extra')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
}; 