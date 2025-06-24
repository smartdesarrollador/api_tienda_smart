<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_pedido' => $this->numero_pedido,
            'user_id' => $this->user_id,
            'metodo_pago_id' => $this->metodo_pago_id,
            'zona_reparto_id' => $this->zona_reparto_id,
            'direccion_validada_id' => $this->direccion_validada_id,
            'total' => (float) $this->total,
            'subtotal' => (float) $this->subtotal,
            'descuento' => (float) $this->descuento,
            'descuento_total' => (float) $this->descuento_total,
            'costo_envio' => (float) $this->costo_envio,
            'igv' => (float) $this->igv,
            'estado' => $this->estado,
            'tipo_pago' => $this->tipo_pago,
            'tipo_entrega' => $this->tipo_entrega,
            'cuotas' => $this->cuotas,
            'monto_cuota' => $this->monto_cuota ? (float) $this->monto_cuota : null,
            'interes_total' => $this->interes_total ? (float) $this->interes_total : null,
            'datos_envio' => $this->datos_envio,
            'metodo_envio' => $this->metodo_envio,
            'datos_cliente' => $this->datos_cliente,
            'cupon_codigo' => $this->cupon_codigo,
            'observaciones' => $this->observaciones,
            'codigo_rastreo' => $this->codigo_rastreo,
            'moneda' => $this->moneda,
            'canal_venta' => $this->canal_venta,
            'tiempo_entrega_estimado' => $this->tiempo_entrega_estimado,
            'fecha_entrega_programada' => $this->fecha_entrega_programada?->toISOString(),
            'fecha_entrega_real' => $this->fecha_entrega_real?->toISOString(),
            'direccion_entrega' => $this->direccion_entrega,
            'telefono_entrega' => $this->telefono_entrega,
            'referencia_entrega' => $this->referencia_entrega,
            'latitud_entrega' => $this->latitud_entrega ? (float) $this->latitud_entrega : null,
            'longitud_entrega' => $this->longitud_entrega ? (float) $this->longitud_entrega : null,
            'repartidor_id' => $this->repartidor_id,
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'total_con_descuento' => $this->total_con_descuento,
            'es_credito' => $this->es_credito,
            'estado_detallado' => $this->getEstadoDetallado(),
            'tiempo_entrega_texto' => $this->getTiempoEntregaTexto(),
            'coordenadas_entrega' => $this->getCoordenadasEntrega(),
            'puede_cancelar' => $this->puedeCanselar(),
            'estimado_entrega' => $this->getEstimadoEntrega(),
            'es_delivery' => $this->tipo_entrega === 'delivery',
            'es_recojo_tienda' => $this->tipo_entrega === 'recojo_tienda',

            // Relaciones
            'usuario' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'nombre' => $this->user->nombre ?? $this->user->name,
                    'email' => $this->user->email,
                    'telefono' => $this->user->telefono ?? null,
                ];
            }),

            'cliente' => $this->whenLoaded('user.cliente', function () {
                return $this->user->cliente ? [
                    'id' => $this->user->cliente->id,
                    'nombre_completo' => $this->user->cliente->nombre_completo,
                    'documento' => $this->user->cliente->documento,
                    'telefono' => $this->user->cliente->telefono,
                    'activo' => $this->user->cliente->activo,
                ] : null;
            }),

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

            'direccion_validada' => $this->whenLoaded('direccionValidada', function () {
                return [
                    'id' => $this->direccionValidada->id,
                    'direccion_id' => $this->direccionValidada->direccion_id,
                    'zona_reparto_id' => $this->direccionValidada->zona_reparto_id,
                    'en_zona_cobertura' => (bool) $this->direccionValidada->en_zona_cobertura,
                    'costo_envio_calculado' => (float) $this->direccionValidada->costo_envio_calculado,
                    'distancia_tienda_km' => $this->direccionValidada->distancia_tienda_km ? (float) $this->direccionValidada->distancia_tienda_km : null,
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

            'detalles' => $this->whenLoaded('detalles', function () {
                return $this->detalles->map(function ($detalle) {
                    return [
                        'id' => $detalle->id,
                        'producto_id' => $detalle->producto_id,
                        'variacion_id' => $detalle->variacion_id,
                        'cantidad' => $detalle->cantidad,
                        'precio_unitario' => (float) $detalle->precio_unitario,
                        'subtotal' => (float) $detalle->subtotal,
                        'descuento' => (float) $detalle->descuento,
                        'impuesto' => (float) $detalle->impuesto,
                        'moneda' => $detalle->moneda,
                        'producto' => $detalle->relationLoaded('producto') ? [
                            'id' => $detalle->producto->id,
                            'nombre' => $detalle->producto->nombre,
                            'sku' => $detalle->producto->sku,
                            'imagen_principal' => $detalle->producto->imagen_principal,
                        ] : null,
                        'variacion' => $detalle->relationLoaded('variacion') && $detalle->variacion ? [
                            'id' => $detalle->variacion->id,
                            'sku' => $detalle->variacion->sku,
                            'nombre' => $detalle->variacion->nombre,
                            'precio' => (float) $detalle->variacion->precio,
                            'precio_oferta' => $detalle->variacion->precio_oferta ? (float) $detalle->variacion->precio_oferta : null,
                        ] : null,
                        // Cargar adicionales del detalle
                        'adicionales' => $detalle->relationLoaded('detalleAdicionales') ? 
                            $detalle->detalleAdicionales->map(function ($detalleAdicional) {
                                return [
                                    'id' => $detalleAdicional->id,
                                    'adicional_id' => $detalleAdicional->adicional_id,
                                    'cantidad' => $detalleAdicional->cantidad,
                                    'precio_unitario' => (float) $detalleAdicional->precio_unitario,
                                    'subtotal' => (float) $detalleAdicional->subtotal,
                                    'adicional' => $detalleAdicional->relationLoaded('adicional') ? [
                                        'id' => $detalleAdicional->adicional->id,
                                        'nombre' => $detalleAdicional->adicional->nombre,
                                    ] : null,
                                ];
                            }) : [],
                    ];
                });
            }),

            'pagos' => $this->whenLoaded('pagos', function () {
                return $this->pagos->map(function ($pago) {
                    return [
                        'id' => $pago->id,
                        'monto' => (float) $pago->monto,
                        'comision' => $pago->comision ? (float) $pago->comision : 0,
                        'numero_cuota' => $pago->numero_cuota,
                        'fecha_pago' => $pago->fecha_pago?->toDateString(),
                        'estado' => $pago->estado,
                        'metodo' => $pago->metodo,
                        'referencia' => $pago->referencia,
                        'codigo_autorizacion' => $pago->codigo_autorizacion,
                        'monto_con_comision' => $pago->monto_con_comision,
                        'es_pagado' => $pago->es_pagado,
                        'metodo_pago' => $pago->relationLoaded('metodoPago') && $pago->metodoPago ? [
                            'id' => $pago->metodoPago->id,
                            'nombre' => $pago->metodoPago->nombre,
                            'tipo' => $pago->metodoPago->tipo,
                        ] : null,
                    ];
                });
            }),

            'cuotas_credito' => $this->whenLoaded('cuotasCredito', function () {
                return $this->cuotasCredito->map(function ($cuota) {
                    return [
                        'id' => $cuota->id,
                        'numero_cuota' => $cuota->numero_cuota,
                        'monto' => (float) $cuota->monto,
                        'fecha_vencimiento' => $cuota->fecha_vencimiento?->toDateString(),
                        'fecha_pago' => $cuota->fecha_pago?->toDateString(),
                        'estado' => $cuota->estado,
                        'observaciones' => $cuota->observaciones,
                    ];
                });
            }),

            // Seguimiento del pedido
            'seguimientos' => $this->whenLoaded('seguimientos', function () {
                return $this->seguimientos->map(function ($seguimiento) {
                    return [
                        'id' => $seguimiento->id,
                        'estado_anterior' => $seguimiento->estado_anterior,
                        'estado_actual' => $seguimiento->estado_actual,
                        'observaciones' => $seguimiento->observaciones,
                        'fecha_cambio' => $seguimiento->fecha_cambio?->toISOString(),
                        'usuario_cambio' => $seguimiento->relationLoaded('usuarioCambio') ? [
                            'id' => $seguimiento->usuarioCambio->id,
                            'nombre' => $seguimiento->usuarioCambio->nombre ?? $seguimiento->usuarioCambio->name,
                        ] : null,
                    ];
                });
            }),

            // Programación de entrega
            'programacion_entrega' => $this->whenLoaded('programacionEntrega', function () {
                return [
                    'id' => $this->programacionEntrega->id,
                    'repartidor_id' => $this->programacionEntrega->repartidor_id,
                    'fecha_programada' => $this->programacionEntrega->fecha_programada?->toISOString(),
                    'hora_inicio_ventana' => $this->programacionEntrega->hora_inicio_ventana,
                    'hora_fin_ventana' => $this->programacionEntrega->hora_fin_ventana,
                    'estado' => $this->programacionEntrega->estado,
                    'orden_ruta' => $this->programacionEntrega->orden_ruta,
                ];
            }),

            // Estadísticas del pedido
            'estadisticas' => [
                'total_pagado' => $this->whenLoaded('pagos', function () {
                    return (float) $this->pagos->where('estado', 'pagado')->sum('monto');
                }),
                'saldo_pendiente' => $this->whenLoaded('pagos', function () {
                    $totalPagado = $this->pagos->where('estado', 'pagado')->sum('monto');
                    return (float) max(0, $this->total - $totalPagado);
                }),
                'cantidad_items' => $this->whenLoaded('detalles', function () {
                    return $this->detalles->sum('cantidad');
                }),
                'productos_diferentes' => $this->whenLoaded('detalles', function () {
                    return $this->detalles->count();
                }),
                'total_adicionales' => $this->whenLoaded('detalles.detalleAdicionales', function () {
                    return (float) $this->detalles->sum(function ($detalle) {
                        return $detalle->detalleAdicionales->sum('subtotal');
                    });
                }),
            ],
        ];
    }

    private function getTiempoEntregaTexto(): ?string
    {
        if (!$this->tiempo_entrega_estimado) {
            return null;
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

    private function getCoordenadasEntrega(): ?array
    {
        if (!$this->latitud_entrega || !$this->longitud_entrega) {
            return null;
        }

        return [
            'lat' => (float) $this->latitud_entrega,
            'lng' => (float) $this->longitud_entrega,
        ];
    }
    
    private function getEstadoDetallado(): array
    {
        return [
            'codigo' => $this->estado,
            'nombre' => match($this->estado) {
                'pendiente' => 'Pendiente de confirmación',
                'aprobado' => 'Aprobado',
                'rechazado' => 'Rechazado',
                'entregado' => 'Entregado',
                'cancelado' => 'Cancelado',
                'enviado' => 'Enviado',
                'devuelto' => 'Devuelto',
                'en_proceso' => 'En proceso',
                default => 'Estado desconocido'
            },
            'descripcion' => $this->getDescripcionEstado(),
            'color' => $this->getColorEstado(),
            'icono' => $this->getIconoEstado(),
        ];
    }
    
    private function getDescripcionEstado(): string
    {
        return match($this->estado) {
            'pendiente' => 'Su pedido está esperando confirmación del equipo de ventas.',
            'aprobado' => 'Su pedido ha sido aprobado y está siendo preparado.',
            'en_proceso' => 'Su pedido está siendo preparado para el envío.',
            'enviado' => 'Su pedido ha sido enviado y está en camino.',
            'entregado' => 'Su pedido ha sido entregado exitosamente.',
            'cancelado' => 'Este pedido ha sido cancelado.',
            'rechazado' => 'Este pedido ha sido rechazado.',
            'devuelto' => 'Este pedido ha sido devuelto.',
            default => 'Estado en actualización.'
        };
    }

    private function getColorEstado(): string
    {
        return match($this->estado) {
            'pendiente' => 'warning',
            'aprobado' => 'info',
            'en_proceso' => 'primary',
            'enviado' => 'secondary',
            'entregado' => 'success',
            'cancelado' => 'danger',
            'rechazado' => 'danger',
            'devuelto' => 'warning',
            default => 'light'
        };
    }

    private function getIconoEstado(): string
    {
        return match($this->estado) {
            'pendiente' => 'clock',
            'aprobado' => 'check-circle',
            'en_proceso' => 'gear',
            'enviado' => 'truck',
            'entregado' => 'check-circle-fill',
            'cancelado' => 'x-circle',
            'rechazado' => 'x-circle-fill',
            'devuelto' => 'arrow-counterclockwise',
            default => 'question-circle'
        };
    }
    
    private function puedeCanselar(): bool
    {
        return in_array($this->estado, ['pendiente', 'aprobado']);
    }
    
    private function getEstimadoEntrega(): ?string
    {
        if ($this->fecha_entrega_programada) {
            return $this->fecha_entrega_programada->format('d/m/Y H:i');
        }

        if ($this->tiempo_entrega_estimado && $this->created_at) {
            $estimado = $this->created_at->addMinutes($this->tiempo_entrega_estimado);
            return $estimado->format('d/m/Y H:i');
        }

        return null;
    }
} 