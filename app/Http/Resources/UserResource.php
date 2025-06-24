<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'dni' => $this->dni,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
            'rol' => $this->rol,
            'limite_credito' => (float) $this->limite_credito,
            'credito_disponible' => (float) $this->limite_credito - (float) ($this->credito_usado ?? 0),
            'verificado' => (bool) $this->verificado,
            'avatar' => $this->avatar,
            'ultimo_login' => $this->ultimo_login?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Contadores relacionados
            'pedidos_count' => $this->when($this->relationLoaded('pedidos'), fn() => $this->pedidos->count()),
            'favoritos_count' => $this->when($this->relationLoaded('favoritos'), fn() => $this->favoritos->count()),
            'comentarios_count' => $this->when($this->relationLoaded('comentarios'), fn() => $this->comentarios->count()),
            
            // Relaciones opcionales
            'direcciones' => DireccionResource::collection($this->whenLoaded('direcciones')),
            'pedidos' => PedidoResource::collection($this->whenLoaded('pedidos')),
        ];
    }
}
