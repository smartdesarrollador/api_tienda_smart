<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteSimpleResource extends JsonResource
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
            'nombre_completo' => $this->nombre_completo_formateado,
            'dni' => $this->dni,
            'telefono' => $this->telefono,
            'email' => $this->user->email ?? null,
            'estado' => $this->estado,
            'verificado' => $this->verificado,
            'limite_credito' => (float) $this->limite_credito,
            'tiene_credito' => $this->tiene_credito,
            'edad' => $this->edad,
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
