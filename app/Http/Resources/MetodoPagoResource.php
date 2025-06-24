<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetodoPagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'tipo' => $this->tipo,
            'tipo_nombre' => $this->getTiposDisponibles()[$this->tipo] ?? $this->tipo,
            'descripcion' => $this->descripcion,
            'logo' => $this->logo,
            'logo_url' => $this->logo_url,
            'activo' => $this->activo,
            'esta_activo' => $this->esta_activo,
            'requiere_verificacion' => $this->requiere_verificacion,
            'comision_porcentaje' => (float) $this->comision_porcentaje,
            'comision_fija' => (float) $this->comision_fija,
            'monto_minimo' => $this->monto_minimo ? (float) $this->monto_minimo : null,
            'monto_maximo' => $this->monto_maximo ? (float) $this->monto_maximo : null,
            'orden' => $this->orden,
            'configuracion' => $this->configuracion,
            'paises_disponibles' => $this->paises_disponibles,
            'proveedor' => $this->proveedor,
            'proveedor_nombre' => $this->getProveedoresDisponibles()[$this->proveedor] ?? $this->proveedor,
            'moneda_soportada' => $this->moneda_soportada,
            'permite_cuotas' => $this->permite_cuotas,
            'permite_cuotas_bool' => $this->permite_cuotas,
            'cuotas_maximas' => $this->cuotas_maximas,
            'instrucciones' => $this->instrucciones,
            'icono_clase' => $this->icono_clase,
            'color_primario' => $this->color_primario,
            'tiempo_procesamiento' => $this->tiempo_procesamiento,
            'tiempo_procesamiento_texto' => $this->getTiempoProcesamiento(),
            
            // Información adicional útil
            'es_tarjeta' => $this->esTarjeta(),
            'es_billetera_digital' => $this->esBilleteraDigital(),
            'es_transferencia' => $this->esTransferencia(),
            'es_efectivo' => $this->esEfectivo(),
            
            // Metadatos
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relaciones (solo si están cargadas)
            'total_pedidos' => $this->whenLoaded('pedidos', fn() => $this->pedidos->count()),
            'total_pagos' => $this->whenLoaded('pagos', fn() => $this->pagos->count()),
            'monto_total_procesado' => $this->whenLoaded('pagos', fn() => $this->pagos->where('estado', 'pagado')->sum('monto')),
        ];
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'tipos_disponibles' => $this->resource::getTiposDisponibles(),
                'proveedores_disponibles' => $this->resource::getProveedoresDisponibles(),
            ],
        ];
    }
} 