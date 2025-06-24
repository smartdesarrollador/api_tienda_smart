<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificacionSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = User::where('rol', 'cliente')->get();

        $tiposNotificacion = [
            'promocion' => [
                'titulo' => 'Nueva promoción disponible',
                'mensaje' => 'Aprovecha nuestro descuento del 20% en toda la tienda. ¡Solo por tiempo limitado!',
            ],
            'pedido' => [
                'titulo' => 'Estado de tu pedido',
                'mensaje' => 'Tu pedido ha sido confirmado y está siendo preparado para el envío.',
            ],
            'credito' => [
                'titulo' => 'Límite de crédito actualizado',
                'mensaje' => 'Tu límite de crédito ha sido incrementado. Ahora puedes realizar compras por un mayor monto.',
            ],
            'bienvenida' => [
                'titulo' => '¡Bienvenido a nuestra tienda!',
                'mensaje' => 'Gracias por registrarte. Disfruta de nuestros productos y ofertas exclusivas.',
            ],
            'stock' => [
                'titulo' => 'Producto disponible',
                'mensaje' => 'El producto que tenías en tu lista de deseos ya está disponible en stock.',
            ],
        ];

        foreach ($clientes as $cliente) {
            // Crear entre 2 y 5 notificaciones por cliente
            $cantidadNotificaciones = rand(2, 5);
            
            for ($i = 0; $i < $cantidadNotificaciones; $i++) {
                $tipoSeleccionado = collect($tiposNotificacion)->random();
                $leido = rand(1, 100) <= 70; // 70% de probabilidad de estar leída
                
                Notificacion::create([
                    'user_id' => $cliente->id,
                    'titulo' => $tipoSeleccionado['titulo'],
                    'mensaje' => $tipoSeleccionado['mensaje'],
                    'tipo' => collect(array_keys($tiposNotificacion))->random(),
                    'leido' => $leido,
                    'created_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }

        // Notificaciones administrativas
        $admin = User::where('rol', 'administrador')->first();
        if ($admin) {
            Notificacion::create([
                'user_id' => $admin->id,
                'titulo' => 'Nuevo pedido recibido',
                'mensaje' => 'Se ha recibido un nuevo pedido que requiere aprobación.',
                'tipo' => 'admin',
                'leido' => false,
                'created_at' => now()->subHours(2),
            ]);

            Notificacion::create([
                'user_id' => $admin->id,
                'titulo' => 'Stock bajo detectado',
                'mensaje' => 'Varios productos tienen stock bajo y requieren reabastecimiento.',
                'tipo' => 'inventario',
                'leido' => false,
                'created_at' => now()->subHours(6),
            ]);
        }
    }
} 