<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistritoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provincia_id' => $this->provincia_id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'codigo_inei' => $this->codigo_inei,
            'codigo_postal' => $this->codigo_postal,
            'latitud' => $this->latitud ? (float) $this->latitud : null,
            'longitud' => $this->longitud ? (float) $this->longitud : null,
            'activo' => (bool) $this->activo,
            'disponible_delivery' => (bool) $this->disponible_delivery,
            'limites_geograficos' => $this->limites_geograficos,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'coordenadas' => $this->latitud && $this->longitud ? [
                'lat' => (float) $this->latitud,
                'lng' => (float) $this->longitud,
            ] : null,

            // Relaciones
            'provincia' => $this->whenLoaded('provincia', function () {
                return [
                    'id' => $this->provincia->id,
                    'nombre' => $this->provincia->nombre,
                    'codigo' => $this->provincia->codigo,
                    'departamento' => $this->provincia->relationLoaded('departamento') ? [
                        'id' => $this->provincia->departamento->id,
                        'nombre' => $this->provincia->departamento->nombre,
                        'codigo' => $this->provincia->departamento->codigo,
                    ] : null,
                ];
            }),

            'zonas_reparto' => $this->whenLoaded('zonasReparto', function () {
                return $this->zonasReparto->map(function ($zona) {
                    return [
                        'id' => $zona->id,
                        'nombre' => $zona->nombre,
                        'activo' => (bool) $zona->activo,
                        'costo_envio' => (float) $zona->costo_envio,
                        'tiempo_entrega_min' => $zona->tiempo_entrega_min,
                        'tiempo_entrega_max' => $zona->tiempo_entrega_max,
                    ];
                });
            }),

            // Información completa de ubicación
            'ubicacion_completa' => $this->whenLoaded('provincia.departamento', function () {
                return $this->getDireccionCompleta();
            }),

            // Estadísticas
            'estadisticas' => [
                'zonas_reparto_activas' => $this->whenLoaded('zonasReparto', function () {
                    return $this->zonasReparto->where('activo', true)->count();
                }),
            ],
        ];
    }

    private function getDireccionCompleta(): string
    {
        $parts = array_filter([
            $this->nombre,
            $this->provincia?->nombre,
            $this->provincia?->departamento?->nombre,
            $this->provincia?->departamento?->pais ?? 'Perú',
        ]);

        return implode(', ', $parts);
    }
} 