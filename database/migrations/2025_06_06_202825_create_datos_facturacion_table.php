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
        Schema::create('datos_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->enum('tipo_documento', ['dni', 'ruc', 'pasaporte', 'carnet_extranjeria']);
            $table->string('numero_documento', 20);
            $table->string('nombre_facturacion'); // Puede ser nombre completo o razón social
            $table->string('razon_social')->nullable(); // Solo para RUC
            $table->string('direccion_fiscal');
            $table->string('distrito_fiscal');
            $table->string('provincia_fiscal');
            $table->string('departamento_fiscal');
            $table->string('codigo_postal_fiscal')->nullable();
            $table->string('telefono_fiscal')->nullable();
            $table->string('email_facturacion')->nullable();
            $table->boolean('predeterminado')->default(false);
            $table->boolean('activo')->default(true);
            $table->string('contacto_empresa')->nullable(); // Para empresas
            $table->string('giro_negocio')->nullable(); // Para RUC
            $table->json('datos_adicionales')->nullable(); // Campos extra flexibles
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datos_facturacion');
    }
};
