<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValorAtributoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'atributo_id' => $this->atributo_id,
            'valor' => $this->valor,
            'codigo' => $this->codigo,
            'imagen' => $this->imagen,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            
            // Información de presentación
            'valor_formateado' => $this->getValorFormateado(),
            'es_color' => !empty($this->codigo) && $this->atributo?->tipo === 'color',
            'tiene_imagen' => !empty($this->imagen),
            
            // Relación con atributo
            'atributo' => $this->when($this->relationLoaded('atributo'), function () {
                return [
                    'id' => $this->atributo->id,
                    'nombre' => $this->atributo->nombre,
                    'slug' => $this->atributo->slug,
                    'tipo' => $this->atributo->tipo,
                ];
            }),
        ];
    }
    
    private function getValorFormateado(): string
    {
        if (!$this->atributo) {
            return $this->valor;
        }
        
        return match ($this->atributo->tipo) {
            'color' => $this->valor . ($this->codigo ? " ({$this->codigo})" : ''),
            'numero' => number_format((float) $this->valor, 0),
            'tamaño' => strtoupper($this->valor),
            default => $this->valor
        };
    }
} 