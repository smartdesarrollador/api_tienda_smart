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
        Schema::create('detalle_adicionales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detalle_pedido_id')->constrained('detalle_pedidos')->onDelete('cascade');
            $table->foreignId('adicional_id')->constrained('adicionales');
            $table->integer('cantidad')->default(1);
            $table->decimal('precio_unitario', 10, 2); // Precio del adicional al momento del pedido
            $table->decimal('subtotal', 10, 2); // cantidad * precio_unitario
            $table->text('observaciones')->nullable(); // Observaciones específicas del adicional
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_adicionales');
    }
};
