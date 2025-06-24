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
        Schema::create('provincias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->constrained('departamentos')->onDelete('cascade');
            $table->string('nombre'); // Lima, Callao, Huarochirí
            $table->string('codigo', 10)->unique(); // LIM01, CAL01
            $table->string('codigo_inei', 10)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provincias');
    }
};
