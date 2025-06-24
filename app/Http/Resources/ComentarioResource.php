<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComentarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'producto_id' => $this->producto_id,
            'comentario' => $this->comentario,
            'calificacion' => $this->calificacion,
            'aprobado' => (bool) $this->aprobado,
            'titulo' => $this->titulo,
            'respuesta_admin' => $this->respuesta_admin,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Información útil del comentario
            'tiempo_transcurrido' => $this->created_at->diffForHumans(),
            'es_recomendado' => $this->calificacion >= 4,
            'tiene_respuesta' => !empty($this->respuesta_admin),
            
            // Relaciones con información limitada del usuario
            'usuario' => [
                'id' => $this->user->id ?? null,
                'nombre' => $this->user->name ?? 'Usuario anónimo',
                'avatar' => $this->user->avatar ?? null,
                'verificado' => $this->user->verificado ?? false,
            ],
            
            // Información del producto si está cargada
            'producto' => $this->when($this->relationLoaded('producto'), function () {
                return [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'slug' => $this->producto->slug,
                    'imagen_principal' => $this->producto->imagen_principal,
                ];
            }),
        ];
    }
} 