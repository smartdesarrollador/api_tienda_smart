<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariacionProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'sku' => $this->sku,
            'precio' => (float) $this->precio,
            'precio_oferta' => $this->precio_oferta ? (float) $this->precio_oferta : null,
            'stock' => $this->stock,
            'activo' => (bool) $this->activo,
            'atributos' => $this->atributos ? json_decode($this->atributos, true) : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Estado del stock
            'disponible' => $this->stock > 0 && $this->activo,
            'estado_stock' => $this->getEstadoStock(),
            
            // Descuento si aplica
            'descuento_porcentaje' => $this->when($this->precio_oferta, function () {
                $descuento = (($this->precio - $this->precio_oferta) / $this->precio) * 100;
                return round($descuento, 2);
            }),
            
            // Relaciones
            'producto' => new ProductoResource($this->whenLoaded('producto')),
            'imagenes' => ImagenProductoResource::collection($this->whenLoaded('imagenes')),
        ];
    }
    
    private function getEstadoStock(): string
    {
        return match (true) {
            $this->stock <= 0 => 'agotado',
            $this->stock <= 5 => 'stock_bajo',
            $this->stock <= 20 => 'stock_limitado',
            default => 'disponible'
        };
    }
} 