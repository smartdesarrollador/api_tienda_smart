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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('dni', 12)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('direccion')->nullable();
            $table->string('nombre_completo')->nullable();
            $table->string('apellidos')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('genero', ['M', 'F', 'O'])->nullable();
            $table->decimal('limite_credito', 12, 2)->default(0);
            $table->boolean('verificado')->default(false);
            $table->string('referido_por')->nullable();
            $table->string('profesion')->nullable();
            $table->string('empresa')->nullable();
            $table->decimal('ingresos_mensuales', 12, 2)->nullable();
            $table->json('preferencias')->nullable(); // Categorías favoritas, etc.
            $table->json('metadata')->nullable(); // Datos adicionales flexibles
            $table->enum('estado', ['activo', 'inactivo', 'bloqueado'])->default('activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
