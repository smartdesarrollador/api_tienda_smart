<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoSimpleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'precio' => (float) $this->precio,
            'precio_oferta' => $this->precio_oferta ? (float) $this->precio_oferta : null,
            'imagen_principal' => $this->imagen_principal,
            'destacado' => (bool) $this->destacado,
            'activo' => (bool) $this->activo,
            'stock' => $this->stock,
            'sku' => $this->sku,
            
            // Información esencial calculada
            'disponible' => $this->stock > 0 && $this->activo,
            'tiene_oferta' => !is_null($this->precio_oferta),
            'porcentaje_descuento' => $this->precio_oferta 
                ? round((($this->precio - $this->precio_oferta) / $this->precio) * 100, 2) 
                : null,
            'precio_final' => $this->precio_oferta ? (float) $this->precio_oferta : (float) $this->precio,
            
            // Información básica de categoría
            'categoria' => [
                'id' => $this->categoria_id,
                'nombre' => $this->categoria->nombre ?? null,
                'slug' => $this->categoria->slug ?? null,
            ],
            
            // Estados de stock simplificados
            'estado_stock' => match (true) {
                $this->stock <= 0 => 'agotado',
                $this->stock <= 5 => 'stock_bajo',
                $this->stock <= 20 => 'stock_limitado',
                default => 'disponible'
            },
        ];
    }
} 