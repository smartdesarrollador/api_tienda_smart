<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class CuponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'descuento' => (float) $this->descuento,
            'tipo' => $this->tipo,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'limite_uso' => $this->limite_uso,
            'usos' => $this->usos,
            'activo' => (bool) $this->activo,
            'descripcion' => $this->descripcion,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Información de validez
            'esta_vigente' => $this->estaVigente(),
            'puede_usarse' => $this->puedeUsarse(),
            'dias_restantes' => $this->getDiasRestantes(),
            'usos_restantes' => $this->getUsosRestantes(),
            'porcentaje_uso' => $this->getPorcentajeUso(),
            
            // Formato de descuento
            'descuento_formateado' => $this->getDescuentoFormateado(),
            'tipo_detallado' => $this->getTipoDetallado(),
            
            // Fechas formateadas
            'periodo_vigencia' => [
                'inicio' => $this->fecha_inicio ? Carbon::parse($this->fecha_inicio)->format('d/m/Y') : null,
                'fin' => $this->fecha_fin ? Carbon::parse($this->fecha_fin)->format('d/m/Y') : null,
                'inicio_formateado' => $this->fecha_inicio ? Carbon::parse($this->fecha_inicio)->diffForHumans() : null,
                'fin_formateado' => $this->fecha_fin ? Carbon::parse($this->fecha_fin)->diffForHumans() : null,
            ],
        ];
    }
    
    private function estaVigente(): bool
    {
        if (!$this->fecha_inicio || !$this->fecha_fin) {
            return false;
        }
        $ahora = now();
        return $ahora->between(
            Carbon::parse($this->fecha_inicio),
            Carbon::parse($this->fecha_fin)
        ) && $this->activo;
    }
    
    private function puedeUsarse(): bool
    {
        return $this->estaVigente() && 
               ($this->limite_uso === null || $this->usos < $this->limite_uso);
    }
    
    private function getDiasRestantes(): ?int
    {
        if (!$this->fecha_fin) {
            return null;
        }
        $finVigencia = Carbon::parse($this->fecha_fin);
        return $finVigencia->isPast() ? 0 : $finVigencia->diffInDays(now());
    }
    
    private function getUsosRestantes(): ?int
    {
        if ($this->limite_uso === null) {
            return null;
        }
        return $this->limite_uso - $this->usos;
    }
    
    private function getPorcentajeUso(): ?float
    {
        if (!$this->limite_uso) {
            return null;
        }
        if ($this->limite_uso == 0) return 0;
        return round(((int)$this->usos / (int)$this->limite_uso) * 100, 2);
    }
    
    private function getDescuentoFormateado(): string
    {
        return $this->tipo === 'porcentaje' 
            ? ((float)$this->descuento) . '%' 
            : "S/ " . number_format((float)$this->descuento, 2);
    }
    
    private function getTipoDetallado(): array
    {
        return [
            'codigo' => $this->tipo,
            'nombre' => $this->tipo === 'porcentaje' ? 'Porcentaje' : 'Monto Fijo',
            'simbolo' => $this->tipo === 'porcentaje' ? '%' : 'S/',
        ];
    }
} 