<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoritoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'producto_id' => $this->producto_id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            
            // Información temporal
            'agregado_hace' => $this->created_at->diffForHumans(),
            'dias_favorito' => $this->created_at->diffInDays(now()),
            
            // Información del producto cuando está cargado
            'producto' => $this->when($this->relationLoaded('producto'), function () {
                return [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'slug' => $this->producto->slug,
                    'precio' => (float) $this->producto->precio,
                    'precio_oferta' => $this->producto->precio_oferta ? (float) $this->producto->precio_oferta : null,
                    'imagen_principal' => $this->producto->imagen_principal,
                    'activo' => (bool) $this->producto->activo,
                    'stock' => $this->producto->stock,
                    'destacado' => (bool) $this->producto->destacado,
                    'categoria' => [
                        'id' => $this->producto->categoria->id ?? null,
                        'nombre' => $this->producto->categoria->nombre ?? null,
                        'slug' => $this->producto->categoria->slug ?? null,
                    ],
                    'disponible' => $this->producto->stock > 0 && $this->producto->activo,
                    'tiene_oferta' => !is_null($this->producto->precio_oferta),
                    'porcentaje_descuento' => $this->producto->precio_oferta 
                        ? round((($this->producto->precio - $this->producto->precio_oferta) / $this->producto->precio) * 100, 2)
                        : null,
                ];
            }),
            
            // Información del usuario cuando está cargado
            'usuario' => $this->when($this->relationLoaded('user'), function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
        ];
    }
} 