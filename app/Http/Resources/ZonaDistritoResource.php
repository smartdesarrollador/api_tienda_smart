<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZonaDistritoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zona_reparto_id' => $this->zona_reparto_id,
            'distrito_id' => $this->distrito_id,
            'costo_envio_personalizado' => $this->costo_envio_personalizado ? (float) $this->costo_envio_personalizado : null,
            'tiempo_adicional' => $this->tiempo_adicional,
            'activo' => (bool) $this->activo,
            'prioridad' => $this->prioridad,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'prioridad_texto' => $this->getPrioridadTexto(),
            'tiempo_adicional_texto' => $this->tiempo_adicional > 0 ? $this->tiempo_adicional . ' minutos' : 'Sin tiempo adicional',
            'costo_envio_efectivo' => $this->getCostoEnvioEfectivo(),
            'costo_envio_formateado' => 'S/ ' . number_format((float) $this->getCostoEnvioEfectivo(), 2),

            // Relaciones
            'zona_reparto' => $this->whenLoaded('zonaReparto', function () {
                return [
                    'id' => $this->zonaReparto->id,
                    'nombre' => $this->zonaReparto->nombre,
                    'slug' => $this->zonaReparto->slug,
                    'costo_envio' => (float) $this->zonaReparto->costo_envio,
                    'tiempo_entrega_min' => $this->zonaReparto->tiempo_entrega_min,
                    'tiempo_entrega_max' => $this->zonaReparto->tiempo_entrega_max,
                    'activo' => (bool) $this->zonaReparto->activo,
                    'disponible_24h' => (bool) $this->zonaReparto->disponible_24h,
                ];
            }),

            'distrito' => $this->whenLoaded('distrito', function () {
                return [
                    'id' => $this->distrito->id,
                    'nombre' => $this->distrito->nombre,
                    'codigo' => $this->distrito->codigo,
                    'activo' => (bool) $this->distrito->activo,
                    'disponible_delivery' => (bool) $this->distrito->disponible_delivery,
                    'provincia' => $this->distrito->relationLoaded('provincia') ? [
                        'id' => $this->distrito->provincia->id,
                        'nombre' => $this->distrito->provincia->nombre,
                        'departamento' => $this->distrito->provincia->relationLoaded('departamento') ? [
                            'id' => $this->distrito->provincia->departamento->id,
                            'nombre' => $this->distrito->provincia->departamento->nombre,
                        ] : null,
                    ] : null,
                ];
            }),

            // Tiempo de entrega calculado
            'tiempo_entrega_calculado' => $this->whenLoaded('zonaReparto', function () {
                return [
                    'tiempo_min' => $this->zonaReparto->tiempo_entrega_min + $this->tiempo_adicional,
                    'tiempo_max' => $this->zonaReparto->tiempo_entrega_max + $this->tiempo_adicional,
                    'tiempo_adicional_aplicado' => $this->tiempo_adicional,
                    'texto' => $this->getTiempoEntregaTexto(),
                ];
            }),
        ];
    }

    private function getPrioridadTexto(): string
    {
        return match($this->prioridad) {
            1 => 'Alta',
            2 => 'Media',
            3 => 'Baja',
            default => 'No definida'
        };
    }

    private function getCostoEnvioEfectivo(): float
    {
        // Si tiene costo personalizado, usar ese, sino usar el de la zona
        if ($this->costo_envio_personalizado !== null) {
            return (float) $this->costo_envio_personalizado;
        }

        return $this->zonaReparto ? (float) $this->zonaReparto->costo_envio : 0.0;
    }

    private function getTiempoEntregaTexto(): string
    {
        if (!$this->zonaReparto) {
            return 'No calculado';
        }

        $tiempoMin = $this->zonaReparto->tiempo_entrega_min + $this->tiempo_adicional;
        $tiempoMax = $this->zonaReparto->tiempo_entrega_max + $this->tiempo_adicional;

        if ($tiempoMin === $tiempoMax) {
            return $tiempoMin . ' minutos';
        }

        return $tiempoMin . ' - ' . $tiempoMax . ' minutos';
    }
} 