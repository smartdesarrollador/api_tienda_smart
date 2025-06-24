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
        Schema::create('adicional_grupo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adicional_id')->constrained('adicionales')->onDelete('cascade');
            $table->foreignId('grupo_adicional_id')->constrained('grupos_adicionales')->onDelete('cascade');
            $table->integer('orden')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adicional_grupo');
    }
};
