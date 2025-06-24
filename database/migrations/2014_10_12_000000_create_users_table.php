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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('dni', 12)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('direccion')->nullable();
            $table->enum('rol', ['autor', 'administrador','cliente','vendedor', 'soporte', 'repartidor'])->default('cliente');
            $table->string('profile_image')->nullable();
            $table->decimal('limite_credito', 12, 2)->default(0);
            $table->boolean('verificado')->default(false);
            $table->string('avatar')->nullable();
            $table->string('referido_por')->nullable();
            $table->timestamp('ultimo_login')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
