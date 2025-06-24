<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventarioMovimientoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'variacion_id' => $this->variacion_id,
            'tipo_movimiento' => $this->tipo_movimiento,
            'cantidad' => $this->cantidad,
            'stock_anterior' => $this->stock_anterior,
            'stock_nuevo' => $this->stock_nuevo,
            'motivo' => $this->motivo,
            'referencia' => $this->referencia,
            'usuario_id' => $this->usuario_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'tipo_movimiento_texto' => $this->getTipoMovimientoTexto(),
            'cantidad_absoluta' => abs($this->cantidad),
            'diferencia_stock' => $this->stock_nuevo - $this->stock_anterior,
            'es_movimiento_positivo' => $this->esMovimientoPositivo(),
            'es_movimiento_valido' => $this->esMovimientoValido(),
            'fecha_formateada' => $this->created_at?->format('d/m/Y H:i'),

            // Relaciones
            'producto' => $this->whenLoaded('producto', function () {
                return [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'sku' => $this->producto->sku,
                    'stock_actual' => $this->producto->stock,
                    'activo' => (bool) $this->producto->activo,
                ];
            }),

            'variacion' => $this->whenLoaded('variacion', function () {
                return [
                    'id' => $this->variacion->id,
                    'nombre' => $this->variacion->nombre,
                    'sku' => $this->variacion->sku,
                    'stock_actual' => $this->variacion->stock,
                    'disponible' => (bool) $this->variacion->disponible,
                ];
            }),

            'usuario' => $this->whenLoaded('usuario', function () {
                return [
                    'id' => $this->usuario->id,
                    'nombre' => $this->usuario->nombre ?? $this->usuario->name,
                    'email' => $this->usuario->email,
                ];
            }),

            // Información del movimiento
            'detalle_movimiento' => [
                'impacto_texto' => $this->getImpactoTexto(),
                'motivo_detallado' => $this->getMotivoDetallado(),
                'requiere_atencion' => $this->requiereAtencion(),
                'es_critico' => $this->esCritico(),
            ],

            // Auditoría del stock
            'auditoria_stock' => [
                'stock_antes' => $this->stock_anterior,
                'stock_despues' => $this->stock_nuevo,
                'cambio' => $this->stock_nuevo - $this->stock_anterior,
                'cambio_esperado' => $this->cantidad,
                'discrepancia' => ($this->stock_nuevo - $this->stock_anterior) - $this->cantidad,
                'auditoria_correcta' => $this->esAuditoriaCorrecta(),
            ],
        ];
    }

    private function getTipoMovimientoTexto(): string
    {
        return match($this->tipo_movimiento) {
            'entrada' => 'Entrada de inventario',
            'salida' => 'Salida de inventario',
            'ajuste' => 'Ajuste de inventario',
            'reserva' => 'Reserva de stock',
            'liberacion' => 'Liberación de reserva',
            default => 'Movimiento desconocido'
        };
    }

    private function esMovimientoPositivo(): bool
    {
        return in_array($this->tipo_movimiento, ['entrada', 'liberacion']) || 
               ($this->tipo_movimiento === 'ajuste' && $this->cantidad > 0);
    }

    private function esMovimientoValido(): bool
    {
        return $this->stock_anterior >= 0 
            && $this->stock_nuevo >= 0 
            && $this->cantidad != 0;
    }

    private function getImpactoTexto(): string
    {
        $cantidadAbs = abs($this->cantidad);
        $accion = $this->esMovimientoPositivo() ? 'aumentó' : 'disminuyó';
        
        return "El stock {$accion} en {$cantidadAbs} unidades";
    }

    private function getMotivoDetallado(): string
    {
        $base = $this->motivo;
        
        if ($this->referencia) {
            $base .= " (Ref: {$this->referencia})";
        }

        return $base;
    }

    private function requiereAtencion(): bool
    {
        return $this->stock_nuevo <= 0 
            || !$this->esMovimientoValido() 
            || !$this->esAuditoriaCorrecta();
    }

    private function esCritico(): bool
    {
        return $this->stock_nuevo < 0 || $this->stock_anterior < 0;
    }

    private function esAuditoriaCorrecta(): bool
    {
        $cambioReal = $this->stock_nuevo - $this->stock_anterior;
        return $cambioReal === $this->cantidad;
    }
} 