<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos');
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago');
            $table->decimal('monto', 12, 2);
            $table->decimal('comision', 10, 2)->nullable(); // Comisión cobrada por el método
            $table->integer('numero_cuota')->nullable();
            $table->date('fecha_pago');
            $table->enum('estado', ['pendiente', 'pagado', 'atrasado', 'fallido', 'cancelado', 'reembolsado']);
            $table->string('metodo')->nullable();
            $table->string('referencia')->nullable();
            $table->string('moneda', 5)->default('PEN');
            $table->json('respuesta_proveedor')->nullable(); // Respuesta completa del gateway de pago
            $table->string('codigo_autorizacion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
}; 