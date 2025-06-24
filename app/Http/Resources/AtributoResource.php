<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AtributoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'tipo' => $this->tipo,
            'filtrable' => (bool) $this->filtrable,
            'visible' => (bool) $this->visible,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Información del tipo de atributo
            'tipo_detallado' => $this->getTipoDetallado(),
            'valores_count' => $this->when($this->relationLoaded('valores'), fn() => $this->valores->count()),
            
            // Relaciones
            'valores' => ValorAtributoResource::collection($this->whenLoaded('valores')),
        ];
    }
    
    private function getTipoDetallado(): array
    {
        return [
            'codigo' => $this->tipo,
            'nombre' => match($this->tipo) {
                'texto' => 'Texto',
                'color' => 'Color',
                'numero' => 'Número',
                'tamaño' => 'Tamaño',
                'booleano' => 'Sí/No',
                default => 'Personalizado'
            },
            'icono' => match($this->tipo) {
                'texto' => 'text',
                'color' => 'palette',
                'numero' => 'numbers',
                'tamaño' => 'resize',
                'booleano' => 'check-circle',
                default => 'settings'
            }
        ];
    }
} 