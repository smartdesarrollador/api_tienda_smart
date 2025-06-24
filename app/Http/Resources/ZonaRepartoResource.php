<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZonaRepartoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'costo_envio' => (float) $this->costo_envio,
            'costo_envio_adicional' => (float) $this->costo_envio_adicional,
            'tiempo_entrega_min' => $this->tiempo_entrega_min,
            'tiempo_entrega_max' => $this->tiempo_entrega_max,
            'pedido_minimo' => $this->pedido_minimo ? (float) $this->pedido_minimo : null,
            'radio_cobertura_km' => $this->radio_cobertura_km ? (float) $this->radio_cobertura_km : null,
            'coordenadas_centro' => $this->coordenadas_centro,
            'poligono_cobertura' => $this->poligono_cobertura,
            'activo' => (bool) $this->activo,
            'disponible_24h' => (bool) $this->disponible_24h,
            'orden' => $this->orden,
            'color_mapa' => $this->color_mapa,
            'observaciones' => $this->observaciones,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'tiempo_entrega_promedio' => $this->tiempo_entrega_min && $this->tiempo_entrega_max 
                ? round(($this->tiempo_entrega_min + $this->tiempo_entrega_max) / 2)
                : null,
            'tiempo_entrega_texto' => $this->getTiempoEntregaTexto(),
            'coordenadas_centro_array' => $this->getCoordenadasCentroArray(),

            // Relaciones
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

            'horarios' => $this->whenLoaded('horarios', function () {
                return $this->horarios->map(function ($horario) {
                    return [
                        'id' => $horario->id,
                        'dia_semana' => $horario->dia_semana,
                        'hora_inicio' => $horario->hora_inicio,
                        'hora_fin' => $horario->hora_fin,
                        'activo' => (bool) $horario->activo,
                    ];
                });
            }),

            'costos_dinamicos' => $this->whenLoaded('costosEnvioDinamicos', function () {
                return $this->costosEnvioDinamicos->map(function ($costo) {
                    return [
                        'id' => $costo->id,
                        'distancia_km' => (float) $costo->distancia_km,
                        'costo_adicional' => (float) $costo->costo_adicional,
                        'activo' => (bool) $costo->activo,
                    ];
                });
            }),

            'excepciones' => $this->whenLoaded('excepciones', function () {
                return $this->excepciones->map(function ($excepcion) {
                    return [
                        'id' => $excepcion->id,
                        'fecha_inicio' => $excepcion->fecha_inicio,
                        'fecha_fin' => $excepcion->fecha_fin,
                        'motivo' => $excepcion->motivo,
                        'costo_envio_especial' => $excepcion->costo_envio_especial ? (float) $excepcion->costo_envio_especial : null,
                        'activo' => (bool) $excepcion->activo,
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
                'tiene_horarios_configurados' => $this->whenLoaded('horarios', function () {
                    return $this->horarios->count() > 0;
                }),
                'horarios_activos' => $this->whenLoaded('horarios', function () {
                    return $this->horarios->where('activo', true)->count();
                }),
            ],
        ];
    }

    private function getTiempoEntregaTexto(): string
    {
        if (!$this->tiempo_entrega_min || !$this->tiempo_entrega_max) {
            return 'No especificado';
        }

        if ($this->tiempo_entrega_min === $this->tiempo_entrega_max) {
            return "{$this->tiempo_entrega_min} minutos";
        }

        return "{$this->tiempo_entrega_min} - {$this->tiempo_entrega_max} minutos";
    }

    private function getCoordenadasCentroArray(): ?array
    {
        if (!$this->coordenadas_centro) {
            return null;
        }

        $coords = explode(',', $this->coordenadas_centro);
        if (count($coords) !== 2) {
            return null;
        }

        return [
            'lat' => (float) trim($coords[0]),
            'lng' => (float) trim($coords[1]),
        ];
    }
} 