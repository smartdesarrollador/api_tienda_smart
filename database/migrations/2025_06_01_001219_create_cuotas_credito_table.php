<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuotas_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->onDelete('cascade');
            $table->integer('numero_cuota');
            $table->decimal('monto_cuota', 12, 2);
            $table->decimal('interes', 12, 2)->nullable();
            $table->decimal('mora', 12, 2)->nullable();
            $table->date('fecha_vencimiento');
            $table->date('fecha_pago')->nullable();
            $table->enum('estado', ['pendiente', 'pagado', 'atrasado', 'condonado']);
            $table->string('moneda', 5)->default('PEN');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas_credito');
    }
}; 