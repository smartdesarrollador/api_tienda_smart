<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoAdicionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'adicional_id' => $this->adicional_id,
            'precio_personalizado' => $this->precio_personalizado ? (float) $this->precio_personalizado : null,
            'obligatorio' => (bool) $this->obligatorio,
            'maximo_cantidad' => $this->maximo_cantidad,
            'orden' => $this->orden,
            'activo' => (bool) $this->activo,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'precio_efectivo' => $this->getPrecioEfectivo(),
            'precio_formateado' => 'S/ ' . number_format($this->getPrecioEfectivo(), 2),
            'cantidad_texto' => $this->getCantidadTexto(),

            // Relaciones
            'producto' => $this->whenLoaded('producto', function () {
                return [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'sku' => $this->producto->sku,
                    'activo' => (bool) $this->producto->activo,
                ];
            }),

            'adicional' => $this->whenLoaded('adicional', function () {
                return [
                    'id' => $this->adicional->id,
                    'nombre' => $this->adicional->nombre,
                    'slug' => $this->adicional->slug,
                    'descripcion' => $this->adicional->descripcion,
                    'precio' => (float) $this->adicional->precio,
                    'imagen' => $this->adicional->imagen,
                    'icono' => $this->adicional->icono,
                    'tipo' => $this->adicional->tipo,
                    'disponible' => (bool) $this->adicional->disponible,
                    'activo' => (bool) $this->adicional->activo,
                    'stock' => $this->adicional->stock,
                    'vegetariano' => (bool) $this->adicional->vegetariano,
                    'vegano' => (bool) $this->adicional->vegano,
                    'imagen_url' => $this->adicional->imagen ? asset('assets/adicionales/' . $this->adicional->imagen) : null,
                    'icono_url' => $this->adicional->icono ? asset('assets/iconos/' . $this->adicional->icono) : null,
                ];
            }),
        ];
    }

    private function getPrecioEfectivo(): float
    {
        // Si tiene precio personalizado, usar ese, sino usar el precio del adicional
        if ($this->precio_personalizado !== null) {
            return (float) $this->precio_personalizado;
        }

        return $this->adicional ? (float) $this->adicional->precio : 0.0;
    }

    private function getCantidadTexto(): string
    {
        if ($this->maximo_cantidad === null) {
            return 'Sin límite';
        }

        if ($this->maximo_cantidad === 1) {
            return 'Máximo 1 unidad';
        }

        return "Máximo {$this->maximo_cantidad} unidades";
    }
} 