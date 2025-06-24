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
        Schema::table('pedidos', function (Blueprint $table) {
            // Modificar user_id para permitir null (para invitados)
            $table->foreignId('user_id')->nullable()->change();
            
            // Agregar campos JSON que faltan si no existen
            if (!Schema::hasColumn('pedidos', 'datos_envio')) {
                $table->json('datos_envio')->nullable();
            }
            if (!Schema::hasColumn('pedidos', 'metodo_envio')) {
                $table->json('metodo_envio')->nullable();
            }
            if (!Schema::hasColumn('pedidos', 'datos_cliente')) {
                $table->json('datos_cliente')->nullable();
            }
            if (!Schema::hasColumn('pedidos', 'descuento')) {
                $table->decimal('descuento', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('pedidos', 'igv')) {
                $table->decimal('igv', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('pedidos', 'numero_pedido')) {
                $table->string('numero_pedido')->unique()->nullable();
            }
            if (!Schema::hasColumn('pedidos', 'cupon_codigo')) {
                $table->string('cupon_codigo')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Revertir user_id a no nullable
            $table->foreignId('user_id')->nullable(false)->change();
            
            // Eliminar campos agregados si existen
            if (Schema::hasColumn('pedidos', 'datos_envio')) {
                $table->dropColumn('datos_envio');
            }
            if (Schema::hasColumn('pedidos', 'metodo_envio')) {
                $table->dropColumn('metodo_envio');
            }
            if (Schema::hasColumn('pedidos', 'datos_cliente')) {
                $table->dropColumn('datos_cliente');
            }
            if (Schema::hasColumn('pedidos', 'descuento')) {
                $table->dropColumn('descuento');
            }
            if (Schema::hasColumn('pedidos', 'igv')) {
                $table->dropColumn('igv');
            }
            if (Schema::hasColumn('pedidos', 'numero_pedido')) {
                $table->dropColumn('numero_pedido');
            }
            if (Schema::hasColumn('pedidos', 'cupon_codigo')) {
                $table->dropColumn('cupon_codigo');
            }
        });
    }
};
