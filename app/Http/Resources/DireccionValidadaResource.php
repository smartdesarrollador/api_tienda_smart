<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DireccionValidadaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direccion_id' => $this->direccion_id,
            'zona_reparto_id' => $this->zona_reparto_id,
            'latitud' => $this->latitud ? (float) $this->latitud : null,
            'longitud' => $this->longitud ? (float) $this->longitud : null,
            'distancia_tienda_km' => $this->distancia_tienda_km ? (float) $this->distancia_tienda_km : null,
            'en_zona_cobertura' => (bool) $this->en_zona_cobertura,
            'costo_envio_calculado' => $this->costo_envio_calculado ? (float) $this->costo_envio_calculado : null,
            'tiempo_entrega_estimado' => $this->tiempo_entrega_estimado,
            'fecha_ultima_validacion' => $this->fecha_ultima_validacion?->toISOString(),
            'observaciones_validacion' => $this->observaciones_validacion,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'coordenadas' => $this->latitud && $this->longitud ? [
                'lat' => (float) $this->latitud,
                'lng' => (float) $this->longitud,
            ] : null,
            'distancia_texto' => $this->getDistanciaTexto(),
            'tiempo_entrega_texto' => $this->getTiempoEntregaTexto(),
            'estado_validacion' => $this->getEstadoValidacion(),

            // Relaciones
            'direccion' => $this->whenLoaded('direccion', function () {
                return [
                    'id' => $this->direccion->id,
                    'direccion' => $this->direccion->direccion,
                    'referencia' => $this->direccion->referencia,
                    'distrito' => $this->direccion->distrito,
                    'provincia' => $this->direccion->provincia,
                    'departamento' => $this->direccion->departamento,
                    'predeterminada' => (bool) $this->direccion->predeterminada,
                ];
            }),

            'zona_reparto' => $this->whenLoaded('zonaReparto', function () {
                return [
                    'id' => $this->zonaReparto->id,
                    'nombre' => $this->zonaReparto->nombre,
                    'slug' => $this->zonaReparto->slug,
                    'costo_envio' => (float) $this->zonaReparto->costo_envio,
                    'tiempo_entrega_min' => $this->zonaReparto->tiempo_entrega_min,
                    'tiempo_entrega_max' => $this->zonaReparto->tiempo_entrega_max,
                    'activo' => (bool) $this->zonaReparto->activo,
                ];
            }),
        ];
    }

    private function getDistanciaTexto(): string
    {
        if (!$this->distancia_tienda_km) {
            return 'No calculada';
        }

        $distancia = (float) $this->distancia_tienda_km;
        
        if ($distancia < 1) {
            return round($distancia * 1000) . ' metros';
        }

        return round($distancia, 1) . ' km';
    }

    private function getTiempoEntregaTexto(): string
    {
        if (!$this->tiempo_entrega_estimado) {
            return 'No calculado';
        }

        $minutos = $this->tiempo_entrega_estimado;
        
        if ($minutos < 60) {
            return $minutos . ' minutos';
        }

        $horas = floor($minutos / 60);
        $minutosRestantes = $minutos % 60;

        if ($minutosRestantes === 0) {
            return $horas . ' hora' . ($horas > 1 ? 's' : '');
        }

        return $horas . 'h ' . $minutosRestantes . 'min';
    }

    private function getEstadoValidacion(): array
    {
        $estado = 'no_validada';
        $mensaje = 'Dirección no validada';

        if ($this->fecha_ultima_validacion) {
            if ($this->en_zona_cobertura) {
                $estado = 'validada_cobertura';
                $mensaje = 'Dirección validada y dentro de zona de cobertura';
            } else {
                $estado = 'validada_sin_cobertura';
                $mensaje = 'Dirección validada pero fuera de zona de cobertura';
            }
        }

        return [
            'codigo' => $estado,
            'mensaje' => $mensaje,
            'tiene_coordenadas' => $this->latitud && $this->longitud,
            'tiene_zona_asignada' => !is_null($this->zona_reparto_id),
        ];
    }
} 