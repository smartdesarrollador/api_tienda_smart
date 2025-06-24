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
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['descuento_producto', 'descuento_categoria', '2x1', '3x2', 'envio_gratis', 'combo']);
            $table->decimal('descuento_porcentaje', 5, 2)->nullable();
            $table->decimal('descuento_monto', 10, 2)->nullable();
            $table->decimal('compra_minima', 10, 2)->nullable();
            $table->datetime('fecha_inicio');
            $table->datetime('fecha_fin');
            $table->boolean('activo')->default(true);
            $table->json('productos_incluidos')->nullable(); // IDs de productos
            $table->json('categorias_incluidas')->nullable(); // IDs de categorías
            $table->json('zonas_aplicables')->nullable(); // IDs de zonas
            $table->integer('limite_uso_total')->nullable();
            $table->integer('limite_uso_cliente')->nullable();
            $table->integer('usos_actuales')->default(0);
            $table->string('imagen')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promociones');
    }
};
