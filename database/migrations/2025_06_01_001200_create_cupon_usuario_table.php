<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupon_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cupon_id')->constrained('cupones');
            $table->foreignId('user_id')->constrained('users');
            $table->boolean('usado')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupon_usuario');
    }
}; 