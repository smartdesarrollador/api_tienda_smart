<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoGrupoAdicionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'grupo_adicional_id' => $this->grupo_adicional_id,
            'orden' => $this->orden,
            'activo' => (bool) $this->activo,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relaciones
            'producto' => $this->whenLoaded('producto', function () {
                return [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'sku' => $this->producto->sku,
                    'activo' => (bool) $this->producto->activo,
                ];
            }),

            'grupo_adicional' => $this->whenLoaded('grupoAdicional', function () {
                return [
                    'id' => $this->grupoAdicional->id,
                    'nombre' => $this->grupoAdicional->nombre,
                    'slug' => $this->grupoAdicional->slug,
                    'descripcion' => $this->grupoAdicional->descripcion,
                    'obligatorio' => (bool) $this->grupoAdicional->obligatorio,
                    'multiple_seleccion' => (bool) $this->grupoAdicional->multiple_seleccion,
                    'minimo_seleccion' => $this->grupoAdicional->minimo_seleccion,
                    'maximo_seleccion' => $this->grupoAdicional->maximo_seleccion,
                    'activo' => (bool) $this->grupoAdicional->activo,
                    
                    // Adicionales del grupo cuando están cargados
                    'adicionales' => $this->grupoAdicional->relationLoaded('adicionales') ? 
                        $this->grupoAdicional->adicionales->map(function ($adicional) {
                            return [
                                'id' => $adicional->id,
                                'nombre' => $adicional->nombre,
                                'precio' => (float) $adicional->precio,
                                'disponible' => (bool) $adicional->disponible,
                                'activo' => (bool) $adicional->activo,
                                'stock' => $adicional->stock,
                                'imagen_url' => $adicional->imagen ? asset('assets/adicionales/' . $adicional->imagen) : null,
                            ];
                        }) : null,
                ];
            }),
        ];
    }
} 