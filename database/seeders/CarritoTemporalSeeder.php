<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CarritoTemporal;
use App\Models\User;
use App\Models\Producto;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CarritoTemporalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener algunos usuarios y productos
        $usuarios = User::take(5)->get();
        $productos = Producto::take(8)->get();

        if ($usuarios->isEmpty() || $productos->isEmpty()) {
            return; // No crear datos si no hay usuarios o productos
        }

        foreach ($usuarios as $usuario) {
            // Cada usuario tiene entre 1-3 productos en su carrito
            $cantidadProductos = mt_rand(1, 3);
            $productosSeleccionados = $productos->random($cantidadProductos);

            foreach ($productosSeleccionados as $index => $producto) {
                $cantidad = mt_rand(1, 3);
                $precio = $producto->precio;
                
                // Simular algunos adicionales
                $adicionales = [];
                if (mt_rand(1, 3) === 1) { // 33% probabilidad de adicionales
                    $adicionales = [
                        ['id' => 1, 'nombre' => 'Queso Cheddar', 'precio' => 4.00],
                        ['id' => 2, 'nombre' => 'Salsa BBQ', 'precio' => 2.50],
                    ];
                }

                CarritoTemporal::create([
                    'user_id' => $usuario->id,
                    'session_id' => 'session_' . $usuario->id . '_' . time(),
                    'producto_id' => $producto->id,
                    'variacion_id' => null, // Sin variaciones por ahora
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'adicionales_seleccionados' => json_encode($adicionales),
                    'observaciones' => mt_rand(1, 4) === 1 ? 'Sin cebolla, por favor' : null,
                    'fecha_expiracion' => Carbon::now()->addHours(24),
                ]);
            }
        }

        // Crear algunos carritos para usuarios no registrados (solo session_id)
        for ($i = 0; $i < 3; $i++) {
            $sessionId = 'guest_session_' . uniqid();
            $productosGuest = $productos->random(mt_rand(1, 2));

            foreach ($productosGuest as $producto) {
                CarritoTemporal::create([
                    'user_id' => null,
                    'session_id' => $sessionId,
                    'producto_id' => $producto->id,
                    'variacion_id' => null,
                    'cantidad' => mt_rand(1, 2),
                    'precio_unitario' => $producto->precio,
                    'adicionales_seleccionados' => json_encode([]),
                    'observaciones' => null,
                    'fecha_expiracion' => Carbon::now()->addHours(2), // Menor tiempo para invitados
                ]);
            }
        }
    }
}
