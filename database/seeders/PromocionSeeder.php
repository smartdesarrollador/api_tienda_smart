<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Promocion;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class PromocionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $promociones = [
            [
                'nombre' => 'Descuento Primer Pedido',
                'slug' => 'descuento-primer-pedido',
                'descripcion' => '20% de descuento en tu primer pedido',
                'tipo' => 'descuento_categoria',
                'descuento_porcentaje' => 20.00,
                'descuento_monto' => null,
                'compra_minima' => 25.00,
                'fecha_inicio' => Carbon::now(),
                'fecha_fin' => Carbon::now()->addMonths(6),
                'activo' => true,
                'productos_incluidos' => null, // Todos los productos
                'categorias_incluidas' => null, // Todas las categorías
                'zonas_aplicables' => null, // Todas las zonas
                'limite_uso_total' => 1000,
                'limite_uso_cliente' => 1,
                'usos_actuales' => 0,
                'imagen' => null,
            ],
            [
                'nombre' => 'Envío Gratis Fin de Semana',
                'slug' => 'envio-gratis-fin-semana',
                'descripcion' => 'Envío gratis en pedidos superiores a 30 soles los fines de semana',
                'tipo' => 'envio_gratis',
                'descuento_porcentaje' => null,
                'descuento_monto' => null,
                'compra_minima' => 30.00,
                'fecha_inicio' => Carbon::now(),
                'fecha_fin' => Carbon::now()->addMonths(3),
                'activo' => true,
                'productos_incluidos' => null,
                'categorias_incluidas' => null,
                'zonas_aplicables' => json_encode([1, 2]), // Zona centro y sur
                'limite_uso_total' => null,
                'limite_uso_cliente' => null,
                'usos_actuales' => 0,
                'imagen' => null,
            ],
            [
                'nombre' => 'Descuento Almuerzo Express',
                'slug' => 'descuento-almuerzo-express',
                'descripcion' => '15% de descuento en categoría comidas',
                'tipo' => 'descuento_categoria',
                'descuento_porcentaje' => 15.00,
                'descuento_monto' => null,
                'compra_minima' => 20.00,
                'fecha_inicio' => Carbon::now(),
                'fecha_fin' => Carbon::now()->addMonths(2),
                'activo' => true,
                'productos_incluidos' => null,
                'categorias_incluidas' => json_encode([1, 2, 3]), // Primeras categorías
                'zonas_aplicables' => null,
                'limite_uso_total' => 500,
                'limite_uso_cliente' => 5,
                'usos_actuales' => 0,
                'imagen' => null,
            ],
            [
                'nombre' => 'Descuento Fijo Miércoles',
                'slug' => 'descuento-fijo-miercoles',
                'descripcion' => '5 soles de descuento todos los miércoles',
                'tipo' => 'descuento_categoria',
                'descuento_porcentaje' => null,
                'descuento_monto' => 5.00,
                'compra_minima' => 35.00,
                'fecha_inicio' => Carbon::now(),
                'fecha_fin' => Carbon::now()->addMonths(4),
                'activo' => true,
                'productos_incluidos' => null,
                'categorias_incluidas' => null,
                'zonas_aplicables' => null,
                'limite_uso_total' => null,
                'limite_uso_cliente' => 1,
                'usos_actuales' => 0,
                'imagen' => null,
            ],
            [
                'nombre' => 'Combo 2x1 Bebidas',
                'slug' => 'combo-2x1-bebidas',
                'descripcion' => '2x1 en bebidas seleccionadas',
                'tipo' => '2x1',
                'descuento_porcentaje' => null,
                'descuento_monto' => null,
                'compra_minima' => 10.00,
                'fecha_inicio' => Carbon::now(),
                'fecha_fin' => Carbon::now()->addMonths(1),
                'activo' => true,
                'productos_incluidos' => json_encode([1, 2, 3]), // IDs de bebidas específicas
                'categorias_incluidas' => null,
                'zonas_aplicables' => null,
                'limite_uso_total' => 200,
                'limite_uso_cliente' => 3,
                'usos_actuales' => 0,
                'imagen' => null,
            ],
            [
                'nombre' => 'Combo Familiar 3x2',
                'slug' => 'combo-familiar-3x2',
                'descripcion' => '3x2 en productos familiares',
                'tipo' => '3x2',
                'descuento_porcentaje' => null,
                'descuento_monto' => null,
                'compra_minima' => 40.00,
                'fecha_inicio' => Carbon::now(),
                'fecha_fin' => Carbon::now()->addMonths(2),
                'activo' => false, // Desactivada por defecto
                'productos_incluidos' => null,
                'categorias_incluidas' => json_encode([1]), // Solo categoría familiar
                'zonas_aplicables' => json_encode([1]), // Solo zona centro
                'limite_uso_total' => 100,
                'limite_uso_cliente' => 2,
                'usos_actuales' => 0,
                'imagen' => null,
            ],
        ];

        foreach ($promociones as $promocion) {
            Promocion::create($promocion);
        }
    }
}
