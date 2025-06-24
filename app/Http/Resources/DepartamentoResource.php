<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'codigo_inei' => $this->codigo_inei,
            'pais' => $this->pais,
            'activo' => (bool) $this->activo,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relaciones
            'provincias' => $this->whenLoaded('provincias', function () {
                return $this->provincias->map(function ($provincia) {
                    return [
                        'id' => $provincia->id,
                        'nombre' => $provincia->nombre,
                        'codigo' => $provincia->codigo,
                        'activo' => (bool) $provincia->activo,
                    ];
                });
            }),

            // Estadísticas
            'estadisticas' => [
                'total_provincias' => $this->whenLoaded('provincias', function () {
                    return $this->provincias->count();
                }),
                'provincias_activas' => $this->whenLoaded('provincias', function () {
                    return $this->provincias->where('activo', true)->count();
                }),
            ],
        ];
    }
} 