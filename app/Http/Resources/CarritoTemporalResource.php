<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarritoTemporalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'session_id' => $this->session_id,
            'producto_id' => $this->producto_id,
            'variacion_id' => $this->variacion_id,
            'cantidad' => $this->cantidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'adicionales_seleccionados' => $this->adicionales_seleccionados,
            'observaciones' => $this->observaciones,
            'fecha_expiracion' => $this->fecha_expiracion?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'subtotal' => $this->getSubtotal(),
            'subtotal_formateado' => 'S/ ' . number_format($this->getSubtotal(), 2),
            'precio_unitario_formateado' => 'S/ ' . number_format((float) $this->precio_unitario, 2),
            'total_adicionales' => $this->getTotalAdicionales(),
            'cantidad_adicionales' => $this->getCantidadAdicionales(),
            'esta_expirado' => $this->estaExpirado(),
            'minutos_para_expiracion' => $this->getMinutosParaExpiracion(),

            // Relaciones
            'usuario' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'nombre' => $this->user->nombre ?? $this->user->name,
                    'email' => $this->user->email,
                ];
            }),

            'producto' => $this->whenLoaded('producto', function () {
                return [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'sku' => $this->producto->sku,
                    'precio' => (float) $this->producto->precio,
                    'imagen_principal' => $this->producto->imagen_principal,
                    'activo' => (bool) $this->producto->activo,
                    'disponible' => (bool) $this->producto->disponible,
                    'stock' => $this->producto->stock,
                ];
            }),

            'variacion' => $this->whenLoaded('variacion', function () {
                return [
                    'id' => $this->variacion->id,
                    'nombre' => $this->variacion->nombre,
                    'sku' => $this->variacion->sku,
                    'precio' => (float) $this->variacion->precio,
                    'precio_oferta' => $this->variacion->precio_oferta ? (float) $this->variacion->precio_oferta : null,
                    'stock' => $this->variacion->stock,
                    'disponible' => (bool) $this->variacion->disponible,
                ];
            }),

            // Adicionales detallados
            'adicionales_detalle' => $this->getAdicionalesDetalle(),

            // Validaciones
            'validaciones' => [
                'producto_disponible' => $this->esProductoDisponible(),
                'stock_suficiente' => $this->esStockSuficiente(),
                'precio_actualizado' => $this->esPrecioActualizado(),
                'carrito_valido' => $this->esCarritoValido(),
            ],
        ];
    }

    private function getSubtotal(): float
    {
        $subtotalProducto = $this->cantidad * $this->precio_unitario;
        $subtotalAdicionales = $this->getTotalAdicionales();
        
        return $subtotalProducto + $subtotalAdicionales;
    }

    private function getTotalAdicionales(): float
    {
        if (!$this->adicionales_seleccionados || !is_array($this->adicionales_seleccionados)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($this->adicionales_seleccionados as $adicionalId => $cantidad) {
            // Aquí se debería obtener el precio del adicional desde la base de datos
            // Por simplicidad, asumimos que está en el JSON como precio
            if (isset($this->adicionales_seleccionados[$adicionalId]['precio'])) {
                $precio = (float) $this->adicionales_seleccionados[$adicionalId]['precio'];
                $total += $precio * $cantidad;
            }
        }

        return $total;
    }

    private function getCantidadAdicionales(): int
    {
        if (!$this->adicionales_seleccionados || !is_array($this->adicionales_seleccionados)) {
            return 0;
        }

        return count($this->adicionales_seleccionados);
    }

    private function estaExpirado(): bool
    {
        return $this->fecha_expiracion ? $this->fecha_expiracion->isPast() : false;
    }

    private function getMinutosParaExpiracion(): ?int
    {
        if (!$this->fecha_expiracion || $this->estaExpirado()) {
            return null;
        }

        return now()->diffInMinutes($this->fecha_expiracion);
    }

    private function getAdicionalesDetalle(): array
    {
        if (!$this->adicionales_seleccionados || !is_array($this->adicionales_seleccionados)) {
            return [];
        }

        $detalle = [];
        foreach ($this->adicionales_seleccionados as $adicionalId => $data) {
            $detalle[] = [
                'adicional_id' => $adicionalId,
                'cantidad' => $data['cantidad'] ?? 1,
                'precio_unitario' => isset($data['precio']) ? (float) $data['precio'] : 0.0,
                'subtotal' => isset($data['precio']) ? (float) $data['precio'] * ($data['cantidad'] ?? 1) : 0.0,
                'nombre' => $data['nombre'] ?? 'Adicional',
            ];
        }

        return $detalle;
    }

    private function esProductoDisponible(): bool
    {
        if ($this->relationLoaded('producto')) {
            return (bool) $this->producto->activo && (bool) $this->producto->disponible;
        }
        
        if ($this->relationLoaded('variacion')) {
            return (bool) $this->variacion->disponible;
        }

        return true; // Si no está cargado, asumimos que está disponible
    }

    private function esStockSuficiente(): bool
    {
        if ($this->relationLoaded('variacion') && $this->variacion->stock !== null) {
            return $this->variacion->stock >= $this->cantidad;
        }
        
        if ($this->relationLoaded('producto') && $this->producto->stock !== null) {
            return $this->producto->stock >= $this->cantidad;
        }

        return true; // Si no está cargado o no tiene stock definido, asumimos que hay stock
    }

    private function esPrecioActualizado(): bool
    {
        $precioActual = 0.0;
        
        if ($this->relationLoaded('variacion')) {
            $precioActual = $this->variacion->precio_oferta ?? $this->variacion->precio;
        } elseif ($this->relationLoaded('producto')) {
            $precioActual = $this->producto->precio;
        }

        if ($precioActual == 0.0) {
            return true; // Si no podemos determinar el precio, asumimos que está actualizado
        }

        return abs($precioActual - $this->precio_unitario) < 0.01;
    }

    private function esCarritoValido(): bool
    {
        return $this->esProductoDisponible() 
            && $this->esStockSuficiente() 
            && !$this->estaExpirado()
            && $this->cantidad > 0;
    }
} 