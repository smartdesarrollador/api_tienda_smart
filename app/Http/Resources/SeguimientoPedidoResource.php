<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeguimientoPedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'estado_anterior' => $this->estado_anterior,
            'estado_actual' => $this->estado_actual,
            'observaciones' => $this->observaciones,
            'usuario_cambio_id' => $this->usuario_cambio_id,
            'latitud_seguimiento' => $this->latitud_seguimiento ? (float) $this->latitud_seguimiento : null,
            'longitud_seguimiento' => $this->longitud_seguimiento ? (float) $this->longitud_seguimiento : null,
            'tiempo_estimado_restante' => $this->tiempo_estimado_restante,
            'fecha_cambio' => $this->fecha_cambio?->toISOString(),
            'notificado_cliente' => (bool) $this->notificado_cliente,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'estado_anterior_texto' => $this->getEstadoTexto($this->estado_anterior),
            'estado_actual_texto' => $this->getEstadoTexto($this->estado_actual),
            'coordenadas_seguimiento' => $this->getCoordenadasSeguimiento(),
            'tiempo_transcurrido' => $this->getTiempoTranscurrido(),
            'tiempo_estimado_texto' => $this->getTiempoEstimadoTexto(),
            'fecha_cambio_formateada' => $this->fecha_cambio ? $this->fecha_cambio->format('d/m/Y H:i') : null,
            'tiene_ubicacion' => $this->tieneUbicacion(),
            'es_cambio_positivo' => $this->esCambioPositivo(),

            // Relaciones
            'pedido' => $this->whenLoaded('pedido', function () {
                return [
                    'id' => $this->pedido->id,
                    'numero_pedido' => $this->pedido->numero_pedido,
                    'estado' => $this->pedido->estado,
                    'total' => (float) $this->pedido->total,
                ];
            }),

            'usuario_cambio' => $this->whenLoaded('usuarioCambio', function () {
                return [
                    'id' => $this->usuarioCambio->id,
                    'nombre' => $this->usuarioCambio->nombre ?? $this->usuarioCambio->name,
                    'email' => $this->usuarioCambio->email,
                ];
            }),

            // Estado del seguimiento
            'estado_seguimiento' => [
                'es_estado_final' => $this->esEstadoFinal(),
                'es_estado_activo' => $this->esEstadoActivo(),
                'puede_cancelar' => $this->puedeCancelar(),
                'requiere_atencion' => $this->requiereAtencion(),
            ],
        ];
    }

    private function getEstadoTexto(?string $estado): ?string
    {
        if (!$estado) {
            return null;
        }

        return match($estado) {
            'pendiente' => 'Pendiente',
            'confirmado' => 'Confirmado',
            'preparando' => 'Preparando',
            'listo' => 'Listo para entregar',
            'enviado' => 'Enviado',
            'entregado' => 'Entregado',
            'cancelado' => 'Cancelado',
            'devuelto' => 'Devuelto',
            default => 'Estado desconocido'
        };
    }

    private function getCoordenadasSeguimiento(): ?array
    {
        if (!$this->latitud_seguimiento || !$this->longitud_seguimiento) {
            return null;
        }

        return [
            'lat' => (float) $this->latitud_seguimiento,
            'lng' => (float) $this->longitud_seguimiento,
        ];
    }

    private function getTiempoTranscurrido(): array
    {
        if (!$this->fecha_cambio) {
            return [
                'minutos' => 0,
                'texto' => 'Sin fecha de cambio',
            ];
        }

        $minutos = $this->fecha_cambio->diffInMinutes(now());
        
        return [
            'minutos' => $minutos,
            'texto' => $this->formatearTiempo($minutos),
        ];
    }

    private function getTiempoEstimadoTexto(): ?string
    {
        if (!$this->tiempo_estimado_restante) {
            return null;
        }

        return $this->formatearTiempo($this->tiempo_estimado_restante);
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

    private function tieneUbicacion(): bool
    {
        return $this->latitud_seguimiento !== null && $this->longitud_seguimiento !== null;
    }

    private function esCambioPositivo(): bool
    {
        $ordenEstados = [
            'pendiente' => 1,
            'confirmado' => 2,
            'preparando' => 3,
            'listo' => 4,
            'enviado' => 5,
            'entregado' => 6,
            'cancelado' => 0,
            'devuelto' => 0,
        ];

        $ordenAnterior = $ordenEstados[$this->estado_anterior] ?? 0;
        $ordenActual = $ordenEstados[$this->estado_actual] ?? 0;

        return $ordenActual > $ordenAnterior;
    }

    private function esEstadoFinal(): bool
    {
        return in_array($this->estado_actual, ['entregado', 'cancelado', 'devuelto']);
    }

    private function esEstadoActivo(): bool
    {
        return in_array($this->estado_actual, ['confirmado', 'preparando', 'listo', 'enviado']);
    }

    private function puedeCancelar(): bool
    {
        return in_array($this->estado_actual, ['pendiente', 'confirmado', 'preparando']);
    }

    private function requiereAtencion(): bool
    {
        return in_array($this->estado_actual, ['cancelado', 'devuelto']) || 
               ($this->tiempo_estimado_restante && $this->tiempo_estimado_restante < 0);
    }
} 