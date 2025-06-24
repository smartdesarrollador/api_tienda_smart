<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSimpleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'rol' => $this->rol,
            'verificado' => (bool) $this->verificado,
            'avatar' => $this->avatar,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            
            // Información de crédito para clientes
            'limite_credito' => $this->when($this->rol === 'cliente', 
                fn() => (float) $this->limite_credito
            ),
            'credito_disponible' => $this->when($this->rol === 'cliente', 
                fn() => (float) $this->limite_credito - (float) ($this->credito_usado ?? 0)
            ),
            
            // Estado del usuario
            'estado' => [
                'activo' => is_null($this->deleted_at),
                'verificado' => (bool) $this->verificado,
                'tiene_credito' => $this->rol === 'cliente' && $this->limite_credito > 0,
            ],
        ];
    }
} 