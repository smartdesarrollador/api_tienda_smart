<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'imagen' => $this->imagen,
            'activo' => (bool) $this->activo,
            'orden' => $this->orden,
            'categoria_padre_id' => $this->categoria_padre_id,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            
            // Contadores
            'productos_count' => $this->when($this->relationLoaded('productos'), fn() => $this->productos->count()),
            'subcategorias_count' => $this->when($this->relationLoaded('subcategorias'), fn() => $this->subcategorias->count()),
            
            // Relaciones jerárquicas
            'categoria_padre' => new self($this->whenLoaded('categoriaPadre')),
            'subcategorias' => self::collection($this->whenLoaded('subcategorias')),
            
            // Productos relacionados (opcional) - solo si está cargado
            'productos' => $this->when($this->relationLoaded('productos'), function () {
                return ProductoResource::collection($this->productos);
            }),
        ];
    }
} 