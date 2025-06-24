<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetalleAdicionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'detalle_pedido_id' => $this->detalle_pedido_id,
            'adicional_id' => $this->adicional_id,
            'cantidad' => $this->cantidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'subtotal' => (float) $this->subtotal,
            'observaciones' => $this->observaciones,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'precio_unitario_formateado' => 'S/ ' . number_format((float) $this->precio_unitario, 2),
            'subtotal_formateado' => 'S/ ' . number_format((float) $this->subtotal, 2),
            'total_calculado' => (float) ($this->cantidad * $this->precio_unitario),
            'es_subtotal_correcto' => abs($this->subtotal - ($this->cantidad * $this->precio_unitario)) < 0.01,

            // Relaciones
            'detalle_pedido' => $this->whenLoaded('detallePedido', function () {
                return [
                    'id' => $this->detallePedido->id,
                    'pedido_id' => $this->detallePedido->pedido_id,
                    'producto_id' => $this->detallePedido->producto_id,
                    'cantidad' => $this->detallePedido->cantidad,
                    'precio_unitario' => (float) $this->detallePedido->precio_unitario,
                    'subtotal' => (float) $this->detallePedido->subtotal,
                ];
            }),

            'adicional' => $this->whenLoaded('adicional', function () {
                return [
                    'id' => $this->adicional->id,
                    'nombre' => $this->adicional->nombre,
                    'slug' => $this->adicional->slug,
                    'descripcion' => $this->adicional->descripcion,
                    'precio_actual' => (float) $this->adicional->precio,
                    'imagen' => $this->adicional->imagen,
                    'icono' => $this->adicional->icono,
                    'tipo' => $this->adicional->tipo,
                    'disponible' => (bool) $this->adicional->disponible,
                    'activo' => (bool) $this->adicional->activo,
                    'vegetariano' => (bool) $this->adicional->vegetariano,
                    'vegano' => (bool) $this->adicional->vegano,
                    'imagen_url' => $this->adicional->imagen ? asset('assets/adicionales/' . $this->adicional->imagen) : null,
                    'icono_url' => $this->adicional->icono ? asset('assets/iconos/' . $this->adicional->icono) : null,
                ];
            }),

            // Información de diferencia de precios
            'diferencia_precio' => $this->whenLoaded('adicional', function () {
                $precioActual = (float) $this->adicional->precio;
                $precioPedido = (float) $this->precio_unitario;
                return [
                    'precio_actual_adicional' => $precioActual,
                    'precio_en_pedido' => $precioPedido,
                    'diferencia' => $precioActual - $precioPedido,
                    'precio_cambio' => abs($precioActual - $precioPedido) > 0.01,
                    'precio_aumento' => $precioActual > $precioPedido,
                ];
            }),
        ];
    }
} 