<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'precio' => (float) $this->precio,
            'precio_oferta' => $this->precio_oferta ? (float) $this->precio_oferta : null,
            'stock' => $this->stock,
            'sku' => $this->sku,
            'codigo_barras' => $this->codigo_barras,
            'imagen_principal' => $this->imagen_principal ? url($this->imagen_principal) : null,
            'destacado' => (bool) $this->destacado,
            'activo' => (bool) $this->activo,
            'categoria_id' => $this->categoria_id,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'garantia' => $this->garantia,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'idioma' => $this->idioma,
            'moneda' => $this->moneda,
            'atributos_extra' => $this->atributos_extra ? json_decode($this->atributos_extra, true) : null,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            
            // Rangos de precios calculados
            'precio_min' => $this->when($this->relationLoaded('variaciones'), 
                fn() => $this->variaciones->min('precio') ? (float) $this->variaciones->min('precio') : (float) $this->precio
            ),
            'precio_max' => $this->when($this->relationLoaded('variaciones'), 
                fn() => $this->variaciones->max('precio') ? (float) $this->variaciones->max('precio') : (float) $this->precio
            ),
            
            // Estadísticas de comentarios
            'rating_promedio' => $this->when(isset($this->comentarios_avg_calificacion), 
                fn() => round((float) $this->comentarios_avg_calificacion, 1)
            ),
            'total_comentarios' => $this->when(isset($this->comentarios_count), fn() => $this->comentarios_count),
            
            // Contadores de relaciones
            'variaciones_count' => $this->when($this->relationLoaded('variaciones'), fn() => $this->variaciones->count()),
            'imagenes_count' => $this->when($this->relationLoaded('imagenes'), fn() => $this->imagenes->count()),
            'favoritos_count' => $this->when($this->relationLoaded('favoritos'), fn() => $this->favoritos->count()),
            
            // Relaciones
            'categoria' => new CategoriaResource($this->whenLoaded('categoria')),
            
            // Relaciones opcionales - solo si existen los Resources
            'variaciones' => $this->when($this->relationLoaded('variaciones'), function () {
                if (class_exists('App\Http\Resources\VariacionProductoResource')) {
                    return \App\Http\Resources\VariacionProductoResource::collection($this->variaciones);
                }
                return $this->variaciones;
            }),
            
            'imagenes' => $this->when($this->relationLoaded('imagenes'), function () {
                if (class_exists('App\Http\Resources\ImagenProductoResource')) {
                    return \App\Http\Resources\ImagenProductoResource::collection($this->imagenes);
                }
                return $this->imagenes;
            }),
            
            'comentarios' => $this->when($this->relationLoaded('comentarios'), function () {
                if (class_exists('App\Http\Resources\ComentarioResource')) {
                    return \App\Http\Resources\ComentarioResource::collection($this->comentarios);
                }
                return $this->comentarios;
            }),
        ];
    }
} 