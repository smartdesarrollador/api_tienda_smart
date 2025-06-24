<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Verificar y agregar campos solo si no existen
            if (!Schema::hasColumn('pedidos', 'numero_pedido')) {
                $table->string('numero_pedido')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('pedidos', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('total');
            }
            if (!Schema::hasColumn('pedidos', 'descuento')) {
                $table->decimal('descuento', 12, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('pedidos', 'costo_envio')) {
                $table->decimal('costo_envio', 12, 2)->default(0)->after('descuento');
            }
            if (!Schema::hasColumn('pedidos', 'igv')) {
                $table->decimal('igv', 12, 2)->default(0)->after('costo_envio');
            }
            if (!Schema::hasColumn('pedidos', 'datos_envio')) {
                $table->json('datos_envio')->nullable()->after('observaciones');
            }
            if (!Schema::hasColumn('pedidos', 'metodo_envio')) {
                $table->json('metodo_envio')->nullable()->after('datos_envio');
            }
            if (!Schema::hasColumn('pedidos', 'datos_cliente')) {
                $table->json('datos_cliente')->nullable()->after('metodo_envio');
            }
            if (!Schema::hasColumn('pedidos', 'cupon_codigo')) {
                $table->string('cupon_codigo')->nullable()->after('datos_cliente');
            }
        });

        // Actualizar pedidos existentes con numero_pedido único solo si el campo existe
        if (Schema::hasColumn('pedidos', 'numero_pedido')) {
            DB::statement("
                UPDATE pedidos 
                SET numero_pedido = CONCAT('PED-', YEAR(created_at), '-', LPAD(id, 6, '0'))
                WHERE numero_pedido IS NULL OR numero_pedido = ''
            ");

            // Agregar la restricción unique después de actualizar los datos
            Schema::table('pedidos', function (Blueprint $table) {
                $table->string('numero_pedido')->nullable(false)->unique()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn([
                'numero_pedido',
                'subtotal',
                'descuento',
                'costo_envio',
                'igv',
                'datos_envio',
                'metodo_envio',
                'datos_cliente',
                'cupon_codigo'
            ]);
        });
    }
};
