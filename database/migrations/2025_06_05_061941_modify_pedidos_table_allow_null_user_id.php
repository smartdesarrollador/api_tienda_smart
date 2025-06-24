<?php

declare(strict_types=1);

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
        Schema::table('pedidos', function (Blueprint $table) {
            // Primero eliminar la clave foránea existente
            $table->dropForeign(['user_id']);
            
            // Modificar la columna para permitir NULL
            $table->unsignedBigInteger('user_id')->nullable()->change();
            
            // Recrear la clave foránea con ON DELETE SET NULL
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Eliminar la clave foránea
            $table->dropForeign(['user_id']);
            
            // Restaurar la columna como NOT NULL
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            
            // Recrear la clave foránea original
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
