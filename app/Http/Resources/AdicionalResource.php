<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdicionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'precio' => (float) $this->precio,
            'imagen' => $this->imagen,
            'icono' => $this->icono,
            'tipo' => $this->tipo,
            'disponible' => (bool) $this->disponible,
            'activo' => (bool) $this->activo,
            'stock' => $this->stock,
            'tiempo_preparacion' => $this->tiempo_preparacion,
            'calorias' => $this->calorias ? (float) $this->calorias : null,
            'informacion_nutricional' => $this->informacion_nutricional,
            'alergenos' => $this->alergenos,
            'vegetariano' => (bool) $this->vegetariano,
            'vegano' => (bool) $this->vegano,
            'orden' => $this->orden,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'agotado' => $this->stock !== null && $this->stock <= 0,
            'precio_formateado' => 'S/ ' . number_format((float) $this->precio, 2),
            'calorias_texto' => $this->calorias ? $this->calorias . ' kcal' : null,
            'tiempo_preparacion_texto' => $this->tiempo_preparacion ? $this->tiempo_preparacion . ' min' : null,
            'caracteristicas_dieteticas' => $this->getCaracteristicasDieteticas(),

            // URLs completas para imágenes
            'imagen_url' => $this->imagen ? asset('assets/adicionales/' . $this->imagen) : null,
            'icono_url' => $this->icono ? asset('assets/iconos/' . $this->icono) : null,

            // Relaciones
            'productos' => $this->whenLoaded('productos', function () {
                return $this->productos->map(function ($producto) {
                    return [
                        'id' => $producto->id,
                        'nombre' => $producto->nombre,
                        'sku' => $producto->sku,
                        'activo' => (bool) $producto->activo,
                    ];
                });
            }),

            'grupos' => $this->whenLoaded('grupos', function () {
                return $this->grupos->map(function ($grupo) {
                    return [
                        'id' => $grupo->id,
                        'nombre' => $grupo->nombre,
                        'activo' => (bool) $grupo->activo,
                        'pivot' => [
                            'orden' => $grupo->pivot->orden ?? 0,
                        ],
                    ];
                });
            }),

            // Estadísticas
            'estadisticas' => [
                'productos_asociados' => $this->whenLoaded('productos', function () {
                    return $this->productos->count();
                }),
                'grupos_asociados' => $this->whenLoaded('grupos', function () {
                    return $this->grupos->count();
                }),
            ],
        ];
    }

    private function getCaracteristicasDieteticas(): array
    {
        $caracteristicas = [];

        if ($this->vegetariano) {
            $caracteristicas[] = 'Vegetariano';
        }

        if ($this->vegano) {
            $caracteristicas[] = 'Vegano';
        }

        if ($this->alergenos && is_array($this->alergenos) && !empty($this->alergenos)) {
            $caracteristicas[] = 'Contiene: ' . implode(', ', $this->alergenos);
        }

        return $caracteristicas;
    }
} 