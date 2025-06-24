<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProvinciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'departamento_id' => $this->departamento_id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'codigo_inei' => $this->codigo_inei,
            'activo' => (bool) $this->activo,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relaciones
            'departamento' => $this->whenLoaded('departamento', function () {
                return [
                    'id' => $this->departamento->id,
                    'nombre' => $this->departamento->nombre,
                    'codigo' => $this->departamento->codigo,
                    'pais' => $this->departamento->pais,
                ];
            }),

            'distritos' => $this->whenLoaded('distritos', function () {
                return $this->distritos->map(function ($distrito) {
                    return [
                        'id' => $distrito->id,
                        'nombre' => $distrito->nombre,
                        'codigo' => $distrito->codigo,
                        'activo' => (bool) $distrito->activo,
                        'disponible_delivery' => (bool) $distrito->disponible_delivery,
                    ];
                });
            }),

            // Estadísticas
            'estadisticas' => [
                'total_distritos' => $this->whenLoaded('distritos', function () {
                    return $this->distritos->count();
                }),
                'distritos_activos' => $this->whenLoaded('distritos', function () {
                    return $this->distritos->where('activo', true)->count();
                }),
                'distritos_con_delivery' => $this->whenLoaded('distritos', function () {
                    return $this->distritos->where('disponible_delivery', true)->count();
                }),
            ],
        ];
    }
} 