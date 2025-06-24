<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExcepcionZonaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zona_reparto_id' => $this->zona_reparto_id,
            'fecha_excepcion' => $this->fecha_excepcion,
            'tipo' => $this->tipo,
            'hora_inicio' => $this->hora_inicio,
            'hora_fin' => $this->hora_fin,
            'costo_especial' => $this->costo_especial ? (float) $this->costo_especial : null,
            'tiempo_especial_min' => $this->tiempo_especial_min,
            'tiempo_especial_max' => $this->tiempo_especial_max,
            'motivo' => $this->motivo,
            'activo' => (bool) $this->activo,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'tipo_texto' => $this->getTipoTexto(),
            'fecha_excepcion_formateada' => $this->fecha_excepcion ? \Carbon\Carbon::parse($this->fecha_excepcion)->format('d/m/Y') : null,
            'horario_especial_texto' => $this->getHorarioEspecialTexto(),
            'tiempo_especial_texto' => $this->getTiempoEspecialTexto(),
            'costo_especial_formateado' => $this->costo_especial ? 'S/ ' . number_format((float) $this->costo_especial, 2) : null,
            'es_fecha_pasada' => $this->esFechaPasada(),
            'es_fecha_hoy' => $this->esFechaHoy(),
            'es_fecha_futura' => $this->esFechaFutura(),
            'esta_activa_ahora' => $this->estaActivaAhora(),

            // Relaciones
            'zona_reparto' => $this->whenLoaded('zonaReparto', function () {
                return [
                    'id' => $this->zonaReparto->id,
                    'nombre' => $this->zonaReparto->nombre,
                    'slug' => $this->zonaReparto->slug,
                    'activo' => (bool) $this->zonaReparto->activo,
                ];
            }),

            // Estado de la excepción
            'estado_excepcion' => [
                'vigente' => $this->esVigente(),
                'aplicable_hoy' => $this->esAplicableHoy(),
                'aplicable_ahora' => $this->esAplicableAhora(),
                'dias_restantes' => $this->getDiasRestantes(),
            ],
        ];
    }

    private function getTipoTexto(): string
    {
        return match($this->tipo) {
            'no_disponible' => 'No disponible',
            'horario_especial' => 'Horario especial',
            'costo_especial' => 'Costo especial',
            'tiempo_especial' => 'Tiempo de entrega especial',
            default => 'Tipo desconocido'
        };
    }

    private function getHorarioEspecialTexto(): ?string
    {
        if ($this->tipo !== 'horario_especial' || !$this->hora_inicio || !$this->hora_fin) {
            return null;
        }

        return $this->hora_inicio . ' - ' . $this->hora_fin;
    }

    private function getTiempoEspecialTexto(): ?string
    {
        if ($this->tipo !== 'tiempo_especial' || (!$this->tiempo_especial_min && !$this->tiempo_especial_max)) {
            return null;
        }

        if ($this->tiempo_especial_min && $this->tiempo_especial_max) {
            if ($this->tiempo_especial_min === $this->tiempo_especial_max) {
                return $this->tiempo_especial_min . ' minutos';
            }
            return $this->tiempo_especial_min . ' - ' . $this->tiempo_especial_max . ' minutos';
        }

        if ($this->tiempo_especial_min) {
            return 'Mínimo ' . $this->tiempo_especial_min . ' minutos';
        }

        if ($this->tiempo_especial_max) {
            return 'Máximo ' . $this->tiempo_especial_max . ' minutos';
        }

        return null;
    }

    private function esFechaPasada(): bool
    {
        return $this->fecha_excepcion ? \Carbon\Carbon::parse($this->fecha_excepcion)->isPast() : false;
    }

    private function esFechaHoy(): bool
    {
        return $this->fecha_excepcion ? \Carbon\Carbon::parse($this->fecha_excepcion)->isToday() : false;
    }

    private function esFechaFutura(): bool
    {
        return $this->fecha_excepcion ? \Carbon\Carbon::parse($this->fecha_excepcion)->isFuture() : false;
    }

    private function estaActivaAhora(): bool
    {
        if (!$this->activo || !$this->esFechaHoy()) {
            return false;
        }

        // Si no tiene horario específico, está activa todo el día
        if (!$this->hora_inicio || !$this->hora_fin) {
            return true;
        }

        $ahora = now()->format('H:i:s');
        return $ahora >= $this->hora_inicio && $ahora <= $this->hora_fin;
    }

    private function esVigente(): bool
    {
        return $this->activo && !$this->esFechaPasada();
    }

    private function esAplicableHoy(): bool
    {
        return $this->activo && $this->esFechaHoy();
    }

    private function esAplicableAhora(): bool
    {
        return $this->esAplicableHoy() && $this->estaActivaAhora();
    }

    private function getDiasRestantes(): ?int
    {
        if (!$this->fecha_excepcion || $this->esFechaPasada()) {
            return null;
        }

        return now()->diffInDays(\Carbon\Carbon::parse($this->fecha_excepcion));
    }
} 