<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdicionalGrupoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adicional_id' => $this->adicional_id,
            'grupo_adicional_id' => $this->grupo_adicional_id,
            'orden' => $this->orden,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relaciones
            'adicional' => $this->whenLoaded('adicional', function () {
                return [
                    'id' => $this->adicional->id,
                    'nombre' => $this->adicional->nombre,
                    'slug' => $this->adicional->slug,
                    'descripcion' => $this->adicional->descripcion,
                    'precio' => (float) $this->adicional->precio,
                    'imagen' => $this->adicional->imagen,
                    'icono' => $this->adicional->icono,
                    'tipo' => $this->adicional->tipo,
                    'disponible' => (bool) $this->adicional->disponible,
                    'activo' => (bool) $this->adicional->activo,
                    'stock' => $this->adicional->stock,
                    'vegetariano' => (bool) $this->adicional->vegetariano,
                    'vegano' => (bool) $this->adicional->vegano,
                    'precio_formateado' => 'S/ ' . number_format((float) $this->adicional->precio, 2),
                    'imagen_url' => $this->adicional->imagen ? asset('assets/adicionales/' . $this->adicional->imagen) : null,
                    'icono_url' => $this->adicional->icono ? asset('assets/iconos/' . $this->adicional->icono) : null,
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
                ];
            }),
        ];
    }
} 