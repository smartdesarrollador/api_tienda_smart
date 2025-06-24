<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImagenProductoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'url_completa' => $this->url ? url($this->url) : null,
            'alt' => $this->alt,
            'orden' => $this->orden,
            'principal' => $this->principal,
            'tipo' => $this->tipo,
            'producto_id' => $this->producto_id,
            'variacion_id' => $this->variacion_id,
            
            // Información del producto
            'producto' => $this->whenLoaded('producto', function () {
                return [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'sku' => $this->producto->sku,
                    'slug' => $this->producto->slug,
                ];
            }),

            // Información de la variación si existe
            'variacion' => $this->whenLoaded('variacion', function () {
                return $this->variacion ? [
                    'id' => $this->variacion->id,
                    'sku' => $this->variacion->sku,
                    'precio' => $this->variacion->precio,
                    'stock' => $this->variacion->stock,
                    'activo' => $this->variacion->activo,
                ] : null;
            }),

            // Metadatos de la imagen
            'metadata' => [
                'es_principal' => $this->principal,
                'tiene_variacion' => !is_null($this->variacion_id),
                'tipo_display' => $this->getTipoDisplay(),
                'orden_display' => $this->orden + 1, // Para mostrar desde 1 en lugar de 0
            ],

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'generated_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Obtener el display name del tipo de imagen
     */
    private function getTipoDisplay(): string
    {
        return match ($this->tipo) {
            'miniatura' => 'Miniatura',
            'galeria' => 'Galería',
            'zoom' => 'Zoom',
            'banner' => 'Banner',
            'detalle' => 'Detalle',
            default => 'General',
        };
    }
} 