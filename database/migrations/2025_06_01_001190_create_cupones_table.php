<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->decimal('descuento', 12, 2);
            $table->enum('tipo', ['porcentaje', 'monto_fijo']);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->integer('limite_uso')->nullable();
            $table->integer('usos')->default(0);
            $table->boolean('activo')->default(true);
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones');
    }
}; 