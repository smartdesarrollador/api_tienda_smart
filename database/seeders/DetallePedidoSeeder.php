<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\VariacionProducto;
use Illuminate\Database\Seeder;

class DetallePedidoSeeder extends Seeder
{
    public function run(): void
    {
        $pedidos = Pedido::all();
        $productos = Producto::all();
        $variaciones = VariacionProducto::all();

        foreach ($pedidos as $pedido) {
            // Crear entre 1 y 4 productos por pedido
            $cantidadProductos = rand(1, 4);
            $totalCalculado = 0;
            
            for ($i = 0; $i < $cantidadProductos; $i++) {
                // Decidir si usar producto base o variación
                $usarVariacion = rand(1, 100) <= 60; // 60% probabilidad de usar variación
                
                if ($usarVariacion && $variaciones->count() > 0) {
                    $variacion = $variaciones->random();
                    $producto = $variacion->producto;
                    $precioUnitario = $variacion->precio_oferta ?? $variacion->precio;
                    $variacionId = $variacion->id;
                } else {
                    $producto = $productos->random();
                    $precioUnitario = $producto->precio_oferta ?? $producto->precio;
                    $variacionId = null;
                }
                
                $cantidad = rand(1, 3);
                $subtotal = $precioUnitario * $cantidad;
                
                // Aplicar descuento aleatorio (0-15%)
                $descuento = $subtotal * (rand(0, 15) / 100);
                
                // Calcular impuesto (18% IGV en Perú)
                $impuesto = ($subtotal - $descuento) * 0.18;
                
                DetallePedido::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $producto->id,
                    'variacion_id' => $variacionId,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotal,
                    'descuento' => $descuento,
                    'impuesto' => $impuesto,
                ]);
                
                $totalCalculado += ($subtotal - $descuento + $impuesto);
            }
            
            // Actualizar el total del pedido si es muy diferente
            if (abs($totalCalculado - $pedido->total) > ($pedido->total * 0.3)) {
                $pedido->update(['total' => round($totalCalculado, 2)]);
                
                // Si es crédito, recalcular cuotas
                if ($pedido->tipo_pago === 'credito' && $pedido->cuotas) {
                    $tasaInteres = 0.08;
                    $interesTotal = ($totalCalculado * $tasaInteres * $pedido->cuotas) / 12;
                    $montoCuota = ($totalCalculado + $interesTotal) / $pedido->cuotas;
                    
                    $pedido->update([
                        'interes_total' => $interesTotal,
                        'monto_cuota' => $montoCuota,
                    ]);
                }
            }
        }
    }
} 