<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->string('imagen')->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->foreignId('categoria_padre_id')->nullable()->constrained('categorias');
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
}; 