<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CostoEnvioDinamicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zona_reparto_id' => $this->zona_reparto_id,
            'distancia_desde_km' => (float) $this->distancia_desde_km,
            'distancia_hasta_km' => (float) $this->distancia_hasta_km,
            'costo_envio' => (float) $this->costo_envio,
            'tiempo_adicional' => (float) $this->tiempo_adicional,
            'activo' => (bool) $this->activo,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'rango_distancia_texto' => $this->getRangoDistanciaTexto(),
            'costo_envio_formateado' => 'S/ ' . number_format((float) $this->costo_envio, 2),
            'tiempo_adicional_texto' => $this->getTiempoAdicionalTexto(),
            'amplitud_rango' => $this->getAmplitudRango(),

            // Relaciones
            'zona_reparto' => $this->whenLoaded('zonaReparto', function () {
                return [
                    'id' => $this->zonaReparto->id,
                    'nombre' => $this->zonaReparto->nombre,
                    'slug' => $this->zonaReparto->slug,
                    'costo_envio' => (float) $this->zonaReparto->costo_envio,
                    'activo' => (bool) $this->zonaReparto->activo,
                ];
            }),

            // Validaciones del rango
            'validaciones' => [
                'rango_valido' => $this->esRangoValido(),
                'distancia_desde_positiva' => $this->distancia_desde_km >= 0,
                'distancia_hasta_mayor' => $this->distancia_hasta_km > $this->distancia_desde_km,
                'costo_positivo' => $this->costo_envio >= 0,
            ],
        ];
    }

    private function getRangoDistanciaTexto(): string
    {
        $desde = number_format((float) $this->distancia_desde_km, 1);
        $hasta = number_format((float) $this->distancia_hasta_km, 1);

        if ($this->distancia_desde_km == 0) {
            return "Hasta {$hasta} km";
        }

        return "De {$desde} a {$hasta} km";
    }

    private function getTiempoAdicionalTexto(): string
    {
        if ($this->tiempo_adicional == 0) {
            return 'Sin tiempo adicional';
        }

        $tiempo = (float) $this->tiempo_adicional;
        
        if ($tiempo < 60) {
            return number_format((float) $tiempo, 0) . ' minutos adicionales';
        }

        $horas = floor($tiempo / 60);
        $minutos = $tiempo % 60;

        if ($minutos == 0) {
            return $horas . ' hora' . ($horas > 1 ? 's' : '') . ' adicionales';
        }

        return $horas . 'h ' . $minutos . 'min adicionales';
    }

    private function getAmplitudRango(): float
    {
        return (float) ($this->distancia_hasta_km - $this->distancia_desde_km);
    }

    private function esRangoValido(): bool
    {
        return $this->distancia_desde_km >= 0 
            && $this->distancia_hasta_km > $this->distancia_desde_km
            && $this->costo_envio >= 0;
    }
} 