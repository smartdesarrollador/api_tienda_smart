<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HorarioZonaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zona_reparto_id' => $this->zona_reparto_id,
            'dia_semana' => $this->dia_semana,
            'hora_inicio' => $this->hora_inicio,
            'hora_fin' => $this->hora_fin,
            'activo' => (bool) $this->activo,
            'dia_completo' => (bool) $this->dia_completo,
            'observaciones' => $this->observaciones,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'dia_semana_numero' => $this->getDiaSemanaNumero(),
            'horario_texto' => $this->getHorarioTexto(),
            'duracion_horas' => $this->getDuracionHoras(),
            'es_horario_valido' => $this->esHorarioValido(),

            // Relaciones
            'zona_reparto' => $this->whenLoaded('zonaReparto', function () {
                return [
                    'id' => $this->zonaReparto->id,
                    'nombre' => $this->zonaReparto->nombre,
                    'slug' => $this->zonaReparto->slug,
                    'activo' => (bool) $this->zonaReparto->activo,
                    'disponible_24h' => (bool) $this->zonaReparto->disponible_24h,
                ];
            }),

            // Estado actual del horario
            'estado_actual' => [
                'es_hoy' => $this->esHoy(),
                'esta_abierto_ahora' => $this->estaAbiertoAhora(),
                'minutos_para_apertura' => $this->getMinutosParaApertura(),
                'minutos_para_cierre' => $this->getMinutosParaCierre(),
            ],
        ];
    }

    private function getDiaSemanaNumero(): int
    {
        return match($this->dia_semana) {
            'lunes' => 1,
            'martes' => 2,
            'miercoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sabado' => 6,
            'domingo' => 7,
            default => 0
        };
    }

    private function getHorarioTexto(): string
    {
        if ($this->dia_completo) {
            return '24 horas';
        }

        if (!$this->hora_inicio || !$this->hora_fin) {
            return 'Horario no definido';
        }

        return $this->hora_inicio . ' - ' . $this->hora_fin;
    }

    private function getDuracionHoras(): ?float
    {
        if ($this->dia_completo) {
            return 24.0;
        }

        if (!$this->hora_inicio || !$this->hora_fin) {
            return null;
        }

        $inicio = \Carbon\Carbon::parse($this->hora_inicio);
        $fin = \Carbon\Carbon::parse($this->hora_fin);

        // Si el horario pasa la medianoche
        if ($fin->lt($inicio)) {
            $fin->addDay();
        }

        return $inicio->diffInHours($fin, true);
    }

    private function esHorarioValido(): bool
    {
        if ($this->dia_completo) {
            return true;
        }

        return $this->hora_inicio && $this->hora_fin;
    }

    private function esHoy(): bool
    {
        $hoy = now()->locale('es')->dayName;
        $diasTraducidos = [
            'Monday' => 'lunes',
            'Tuesday' => 'martes', 
            'Wednesday' => 'miercoles',
            'Thursday' => 'jueves',
            'Friday' => 'viernes',
            'Saturday' => 'sabado',
            'Sunday' => 'domingo'
        ];

        $diaHoyEspanol = $diasTraducidos[now()->englishDayOfWeek] ?? null;
        return $diaHoyEspanol === $this->dia_semana;
    }

    private function estaAbiertoAhora(): bool
    {
        if (!$this->activo || !$this->esHoy()) {
            return false;
        }

        if ($this->dia_completo) {
            return true;
        }

        if (!$this->hora_inicio || !$this->hora_fin) {
            return false;
        }

        $ahora = now()->format('H:i:s');
        $inicio = $this->hora_inicio;
        $fin = $this->hora_fin;

        // Horario normal (no cruza medianoche)
        if ($inicio <= $fin) {
            return $ahora >= $inicio && $ahora <= $fin;
        }

        // Horario que cruza medianoche
        return $ahora >= $inicio || $ahora <= $fin;
    }

    private function getMinutosParaApertura(): ?int
    {
        if (!$this->activo || $this->dia_completo || !$this->hora_inicio) {
            return null;
        }

        if ($this->estaAbiertoAhora()) {
            return 0;
        }

        $hoy = now();
        $apertura = $hoy->copy()->setTimeFromTimeString($this->hora_inicio);

        // Si ya pasó la hora de apertura hoy, calcular para la próxima semana
        if ($hoy->gt($apertura)) {
            $apertura->addWeek();
        }

        return $hoy->diffInMinutes($apertura);
    }

    private function getMinutosParaCierre(): ?int
    {
        if (!$this->activo || $this->dia_completo || !$this->hora_fin) {
            return null;
        }

        if (!$this->estaAbiertoAhora()) {
            return null;
        }

        $hoy = now();
        $cierre = $hoy->copy()->setTimeFromTimeString($this->hora_fin);

        // Si el horario cruza medianoche y aún no es medianoche
        if ($this->hora_fin < $this->hora_inicio && $hoy->format('H:i:s') >= $this->hora_inicio) {
            $cierre->addDay();
        }

        return $hoy->diffInMinutes($cierre);
    }
} 