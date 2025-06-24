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
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Visa, Mastercard, Yape, Plin, Transferencia, etc.
            $table->string('slug')->unique();
            $table->string('tipo'); // tarjeta_credito, tarjeta_debito, billetera_digital, transferencia, efectivo
            $table->text('descripcion')->nullable();
            $table->string('logo')->nullable(); // URL del logo
            $table->boolean('activo')->default(true);
            $table->boolean('requiere_verificacion')->default(false);
            $table->decimal('comision_porcentaje', 5, 3)->default(0); // Ej: 3.5% = 3.500
            $table->decimal('comision_fija', 10, 2)->default(0); // Ej: S/ 2.50
            $table->decimal('monto_minimo', 10, 2)->nullable(); // Monto mínimo para usar este método
            $table->decimal('monto_maximo', 12, 2)->nullable(); // Monto máximo para usar este método
            $table->integer('orden')->default(0); // Para ordenar en el frontend
            $table->json('configuracion')->nullable(); // API keys, endpoints, configuraciones específicas
            $table->json('paises_disponibles')->nullable(); // ['PE', 'CO', 'MX'] - países donde está disponible
            $table->string('proveedor')->nullable(); // Culqi, MercadoPago, PayPal, etc.
            $table->string('moneda_soportada', 5)->default('PEN'); // Moneda principal soportada
            $table->boolean('permite_cuotas')->default(false);
            $table->integer('cuotas_maximas')->nullable();
            $table->text('instrucciones')->nullable(); // Instrucciones para el cliente
            $table->string('icono_clase')->nullable(); // Clase CSS del icono (ej: fas fa-credit-card)
            $table->string('color_primario')->nullable(); // Color hexadecimal del método
            $table->integer('tiempo_procesamiento')->nullable(); // Tiempo en minutos para procesar
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metodos_pago');
    }
};
