<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'metodo_pago_id' => $this->metodo_pago_id,
            'monto' => (float) $this->monto,
            'comision' => $this->comision ? (float) $this->comision : 0,
            'numero_cuota' => $this->numero_cuota,
            'fecha_pago' => $this->fecha_pago,
            'estado' => $this->estado,
            'metodo' => $this->metodo,
            'referencia' => $this->referencia,
            'moneda' => $this->moneda,
            'respuesta_proveedor' => $this->respuesta_proveedor,
            'codigo_autorizacion' => $this->codigo_autorizacion,
            'observaciones' => $this->observaciones,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Información calculada
            'monto_con_comision' => $this->monto_con_comision,
            'es_pagado' => $this->es_pagado,
            'es_pendiente' => $this->es_pendiente,
            'es_fallido' => $this->es_fallido,
            'comision_porcentual' => $this->calcularComisionPorcentual(),
            
            // Estado detallado
            'estado_detallado' => $this->getEstadoDetallado(),
            'metodo_detallado' => $this->getMetodoDetallado(),
            
            // Información del pago
            'es_cuota' => !is_null($this->numero_cuota),
            'dias_desde_pago' => $this->fecha_pago ? 
                \Carbon\Carbon::parse($this->fecha_pago)->diffInDays(now()) : null,

            // Relaciones
            'metodo_pago' => $this->whenLoaded('metodoPago', function () {
                return [
                    'id' => $this->metodoPago->id,
                    'nombre' => $this->metodoPago->nombre,
                    'tipo' => $this->metodoPago->tipo,
                    'slug' => $this->metodoPago->slug,
                    'logo_url' => $this->metodoPago->logo_url,
                    'permite_cuotas' => $this->metodoPago->permite_cuotas,
                    'comision_porcentaje' => (float) $this->metodoPago->comision_porcentaje,
                    'comision_fija' => (float) $this->metodoPago->comision_fija,
                ];
            }),

            'pedido' => $this->whenLoaded('pedido', function () {
                return [
                    'id' => $this->pedido->id,
                    'numero_pedido' => $this->pedido->numero_pedido,
                    'total' => (float) $this->pedido->total,
                    'estado' => $this->pedido->estado,
                    'tipo_pago' => $this->pedido->tipo_pago,
                    'cuotas' => $this->pedido->cuotas,
                    'moneda' => $this->pedido->moneda,
                    'usuario' => $this->whenLoaded('pedido.user', function () {
                        return [
                            'id' => $this->pedido->user->id,
                            'nombre' => $this->pedido->user->nombre ?? $this->pedido->user->name,
                            'email' => $this->pedido->user->email,
                        ];
                    })
                ];
            }),
        ];
    }
    
    private function getEstadoDetallado(): array
    {
        return [
            'codigo' => $this->estado,
            'nombre' => match($this->estado) {
                'pendiente' => 'Pendiente',
                'pagado' => 'Pagado',
                'atrasado' => 'Atrasado',
                'fallido' => 'Fallido',
                default => 'Estado desconocido'
            },
            'color' => match($this->estado) {
                'pendiente' => 'yellow',
                'pagado' => 'green',
                'atrasado' => 'red',
                'fallido' => 'red',
                default => 'gray'
            }
        ];
    }
    
    private function getMetodoDetallado(): array
    {
        return [
            'codigo' => $this->metodo,
            'nombre' => match($this->metodo) {
                'efectivo' => 'Efectivo',
                'tarjeta' => 'Tarjeta de Crédito/Débito',
                'transferencia' => 'Transferencia Bancaria',
                'yape' => 'Yape',
                'plin' => 'Plin',
                'paypal' => 'PayPal',
                default => 'Método no especificado'
            },
            'requiere_referencia' => in_array($this->metodo, ['transferencia', 'yape', 'plin', 'paypal'])
        ];
    }
} 