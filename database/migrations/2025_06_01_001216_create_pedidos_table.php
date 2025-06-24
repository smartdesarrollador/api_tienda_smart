<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('metodo_pago_id')->nullable()->constrained('metodos_pago');
             $table->foreignId('zona_reparto_id')->nullable()->constrained('zonas_reparto');
            $table->foreignId('direccion_validada_id')->nullable()->constrained('direcciones_validadas');
            $table->decimal('subtotal', 12, 2); // Total sin costo de envío
            $table->decimal('costo_envio', 10, 2)->default(0); // Costo de delivery
            $table->decimal('total', 12, 2);
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'entregado', 'cancelado', 'enviado', 'devuelto', 'en_proceso']);
            $table->enum('tipo_pago', ['contado', 'credito', 'transferencia', 'tarjeta', 'yape', 'plin', 'paypal']);
            $table->enum('tipo_entrega', ['delivery', 'recojo_tienda'])->default('delivery');
            $table->integer('cuotas')->nullable();
            $table->decimal('monto_cuota', 12, 2)->nullable();
            $table->decimal('interes_total', 12, 2)->nullable();
            $table->decimal('descuento_total', 12, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('codigo_rastreo')->nullable();
            $table->string('moneda', 5)->default('PEN');
            $table->string('canal_venta')->nullable();
            $table->integer('tiempo_entrega_estimado')->nullable(); // En minutos
            $table->datetime('fecha_entrega_programada')->nullable();
            $table->datetime('fecha_entrega_real')->nullable();
            $table->string('direccion_entrega')->nullable(); // Copia de la dirección al momento del pedido
            $table->string('telefono_entrega')->nullable();
            $table->string('referencia_entrega')->nullable();
            $table->decimal('latitud_entrega', 10, 8)->nullable();
            $table->decimal('longitud_entrega', 11, 8)->nullable();
            $table->foreignId('repartidor_id')->nullable()->constrained('users'); // Usuario con rol repartidor
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
}; 