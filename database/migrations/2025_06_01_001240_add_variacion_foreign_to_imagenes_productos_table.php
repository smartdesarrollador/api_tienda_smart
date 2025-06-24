<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imagenes_productos', function (Blueprint $table) {
            $table->foreign('variacion_id')->references('id')->on('variaciones_productos')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('imagenes_productos', function (Blueprint $table) {
            $table->dropForeign(['variacion_id']);
        });
    }
}; 