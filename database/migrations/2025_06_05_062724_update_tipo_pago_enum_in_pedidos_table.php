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
        // Cambiar el tipo de la columna tipo_pago de ENUM a VARCHAR para mayor flexibilidad
        DB::statement("ALTER TABLE pedidos MODIFY COLUMN tipo_pago VARCHAR(50) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurar el enum original
        DB::statement("ALTER TABLE pedidos MODIFY COLUMN tipo_pago ENUM('contado', 'credito', 'transferencia', 'tarjeta', 'yape', 'plin', 'paypal') NOT NULL");
    }
};
