<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfiguracionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $valorProcesado = $this->procesarValorSegunTipo();
        
        return [
            'id' => $this->id,
            'clave' => $this->clave,
            'valor' => $this->valor,
            'valor_procesado' => $valorProcesado,
            'tipo' => $this->tipo,
            'descripcion' => $this->descripcion,
            'grupo' => $this->grupo,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
    
    /**
     * Procesar el valor según su tipo.
     *
     * @return mixed
     */
    protected function procesarValorSegunTipo()
    {
        switch ($this->tipo) {
            case 'json':
                return json_decode($this->valor, true);
            case 'booleano':
                return (bool) $this->valor;
            case 'numero':
                return (int) $this->valor;
            default:
                return $this->valor;
        }
    }
}
