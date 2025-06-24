<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramacionEntregaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'repartidor_id' => $this->repartidor_id,
            'fecha_programada' => $this->fecha_programada?->toISOString(),
            'hora_inicio_ventana' => $this->hora_inicio_ventana,
            'hora_fin_ventana' => $this->hora_fin_ventana,
            'estado' => $this->estado,
            'orden_ruta' => $this->orden_ruta,
            'notas_repartidor' => $this->notas_repartidor,
            'hora_salida' => $this->hora_salida?->toISOString(),
            'hora_llegada' => $this->hora_llegada?->toISOString(),
            'motivo_fallo' => $this->motivo_fallo,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'estado_texto' => $this->getEstadoTexto(),
            'fecha_programada_formateada' => $this->fecha_programada?->format('d/m/Y'),
            'ventana_entrega_texto' => $this->getVentanaEntregaTexto(),
            'duracion_ventana_minutos' => $this->getDuracionVentanaMinutos(),
            'tiempo_transcurrido_entrega' => $this->getTiempoTranscurridoEntrega(),
            'orden_ruta_texto' => $this->orden_ruta ? "#{$this->orden_ruta}" : 'Sin orden',

            // Estados y validaciones
            'estado_programacion' => [
                'es_programacion_futura' => $this->esProgramacionFutura(),
                'es_programacion_hoy' => $this->esProgramacionHoy(),
                'es_programacion_pasada' => $this->esProgramacionPasada(),
                'esta_en_ventana' => $this->estaEnVentana(),
                'ventana_expirada' => $this->esVentanaExpirada(),
                'requiere_atencion' => $this->requiereAtencion(),
                'es_entrega_exitosa' => $this->esEntregaExitosa(),
                'tiene_retraso' => $this->tieneRetraso(),
            ],

            // Relaciones
            'pedido' => $this->whenLoaded('pedido', function () {
                return [
                    'id' => $this->pedido->id,
                    'numero_pedido' => $this->pedido->numero_pedido,
                    'total' => (float) $this->pedido->total,
                    'estado' => $this->pedido->estado,
                    'datos_envio' => $this->pedido->datos_envio,
                ];
            }),

            'repartidor' => $this->whenLoaded('repartidor', function () {
                return [
                    'id' => $this->repartidor->id,
                    'nombre' => $this->repartidor->nombre ?? $this->repartidor->name,
                    'email' => $this->repartidor->email,
                    'telefono' => $this->repartidor->telefono ?? null,
                ];
            }),

            // Tiempos de entrega
            'tiempos_entrega' => [
                'hora_salida_formateada' => $this->hora_salida?->format('H:i'),
                'hora_llegada_formateada' => $this->hora_llegada?->format('H:i'),
                'tiempo_total_entrega' => $this->getTiempoTotalEntrega(),
                'tiempo_total_texto' => $this->getTiempoTotalTexto(),
                'puntualidad' => $this->getPuntualidad(),
            ],

            // Información de la ruta
            'info_ruta' => [
                'es_primera_entrega' => $this->orden_ruta === 1,
                'es_ultima_entrega' => $this->esUltimaEntrega(),
                'posicion_en_ruta' => $this->orden_ruta ?? 0,
                'estado_ruta' => $this->getEstadoRuta(),
            ],
        ];
    }

    private function getEstadoTexto(): string
    {
        return match($this->estado) {
            'programado' => 'Programado',
            'en_ruta' => 'En ruta',
            'entregado' => 'Entregado',
            'fallido' => 'Falló la entrega',
            'reprogramado' => 'Reprogramado',
            default => 'Estado desconocido'
        };
    }

    private function getVentanaEntregaTexto(): string
    {
        if (!$this->hora_inicio_ventana || !$this->hora_fin_ventana) {
            return 'Ventana no definida';
        }

        return $this->hora_inicio_ventana . ' - ' . $this->hora_fin_ventana;
    }

    private function getDuracionVentanaMinutos(): ?int
    {
        if (!$this->hora_inicio_ventana || !$this->hora_fin_ventana) {
            return null;
        }

        $inicio = \Carbon\Carbon::parse($this->hora_inicio_ventana);
        $fin = \Carbon\Carbon::parse($this->hora_fin_ventana);

        return $inicio->diffInMinutes($fin);
    }

    private function getTiempoTranscurridoEntrega(): ?array
    {
        if (!$this->hora_salida) {
            return null;
        }

        $transcurrido = $this->hora_salida->diffInMinutes(now());
        
        return [
            'minutos' => $transcurrido,
            'texto' => $this->formatearTiempo($transcurrido),
        ];
    }

    private function esProgramacionFutura(): bool
    {
        return $this->fecha_programada && $this->fecha_programada->isFuture();
    }

    private function esProgramacionHoy(): bool
    {
        return $this->fecha_programada && $this->fecha_programada->isToday();
    }

    private function esProgramacionPasada(): bool
    {
        return $this->fecha_programada && $this->fecha_programada->isPast();
    }

    private function estaEnVentana(): bool
    {
        if (!$this->esProgramacionHoy() || !$this->hora_inicio_ventana || !$this->hora_fin_ventana) {
            return false;
        }

        $ahora = now()->format('H:i:s');
        return $ahora >= $this->hora_inicio_ventana && $ahora <= $this->hora_fin_ventana;
    }

    private function esVentanaExpirada(): bool
    {
        if (!$this->esProgramacionHoy() || !$this->hora_fin_ventana) {
            return false;
        }

        return now()->format('H:i:s') > $this->hora_fin_ventana;
    }

    private function requiereAtencion(): bool
    {
        return $this->estado === 'fallido' || 
               $this->estado === 'reprogramado' ||
               ($this->esVentanaExpirada() && $this->estado !== 'entregado');
    }

    private function esEntregaExitosa(): bool
    {
        return $this->estado === 'entregado' && $this->hora_llegada;
    }

    private function tieneRetraso(): bool
    {
        if (!$this->hora_llegada || !$this->hora_fin_ventana) {
            return false;
        }

        $horaLlegada = $this->hora_llegada->format('H:i:s');
        return $horaLlegada > $this->hora_fin_ventana;
    }

    private function getTiempoTotalEntrega(): ?int
    {
        if (!$this->hora_salida || !$this->hora_llegada) {
            return null;
        }

        return $this->hora_salida->diffInMinutes($this->hora_llegada);
    }

    private function getTiempoTotalTexto(): ?string
    {
        $tiempo = $this->getTiempoTotalEntrega();
        
        return $tiempo ? $this->formatearTiempo($tiempo) : null;
    }

    private function getPuntualidad(): ?string
    {
        if (!$this->esEntregaExitosa()) {
            return null;
        }

        if ($this->tieneRetraso()) {
            return 'Con retraso';
        }

        if ($this->estaEnVentana()) {
            return 'Puntual';
        }

        return 'Entregado antes de tiempo';
    }

    private function esUltimaEntrega(): bool
    {
        // Esta información requeriría conocer el total de entregas del repartidor
        // Por simplicidad, retornamos false
        return false;
    }

    private function getEstadoRuta(): string
    {
        return match($this->estado) {
            'programado' => 'Pendiente',
            'en_ruta' => 'En progreso',
            'entregado' => 'Completado',
            'fallido' => 'Falló',
            'reprogramado' => 'Necesita reprogramación',
            default => 'Indefinido'
        };
    }

    private function formatearTiempo(int $minutos): string
    {
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
} 