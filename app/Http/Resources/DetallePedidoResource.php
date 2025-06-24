<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetallePedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'producto_id' => $this->producto_id,
            'variacion_id' => $this->variacion_id,
            'cantidad' => $this->cantidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'subtotal' => (float) $this->subtotal,
            'descuento' => $this->descuento ? (float) $this->descuento : null,
            'impuesto' => $this->impuesto ? (float) $this->impuesto : null,
            'moneda' => $this->moneda,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            
            // Cálculos
            'total_linea' => (float) $this->subtotal + (float) ($this->impuesto ?? 0) - (float) ($this->descuento ?? 0),
            'precio_con_descuento' => $this->descuento 
                ? (float) $this->precio_unitario - ((float) $this->descuento / $this->cantidad)
                : (float) $this->precio_unitario,
            
            // Relaciones
            'producto' => new ProductoResource($this->whenLoaded('producto')),
            'variacion' => new VariacionProductoResource($this->whenLoaded('variacion')),
        ];
    }
} 