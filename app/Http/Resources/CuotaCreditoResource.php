<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class CuotaCreditoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'numero_cuota' => $this->numero_cuota,
            'monto_cuota' => (float) $this->monto_cuota,
            'interes' => $this->interes ? (float) $this->interes : null,
            'mora' => $this->mora ? (float) $this->mora : null,
            'fecha_vencimiento' => $this->fecha_vencimiento,
            'fecha_pago' => $this->fecha_pago,
            'estado' => $this->estado,
            'moneda' => $this->moneda,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Cálculos de vencimiento
            'dias_vencimiento' => $this->getDiasVencimiento(),
            'esta_vencida' => $this->estaVencida(),
            'monto_total' => $this->getMontoTotal(),
            'dias_atraso' => $this->getDiasAtraso(),
            
            // Estado detallado
            'estado_detallado' => $this->getEstadoDetallado(),
            'urgencia' => $this->getUrgencia(),
            
            // Información de fecha legible
            'fecha_vencimiento_formateada' => Carbon::parse($this->fecha_vencimiento)->format('d/m/Y'),
            'fecha_pago_formateada' => $this->fecha_pago ? 
                Carbon::parse($this->fecha_pago)->format('d/m/Y H:i') : null,
        ];
    }
    
    private function getDiasVencimiento(): int
    {
        return Carbon::parse($this->fecha_vencimiento)->diffInDays(now(), false);
    }
    
    private function estaVencida(): bool
    {
        return $this->estado !== 'pagado' && 
               Carbon::parse($this->fecha_vencimiento)->isPast();
    }
    
    private function getMontoTotal(): float
    {
        return (float) $this->monto_cuota + 
               (float) ($this->interes ?? 0) + 
               (float) ($this->mora ?? 0);
    }
    
    private function getDiasAtraso(): int
    {
        if (!$this->estaVencida()) {
            return 0;
        }
        
        return Carbon::parse($this->fecha_vencimiento)->diffInDays(now());
    }
    
    private function getEstadoDetallado(): array
    {
        return [
            'codigo' => $this->estado,
            'nombre' => match($this->estado) {
                'pendiente' => 'Pendiente',
                'pagado' => 'Pagada',
                'atrasado' => 'Atrasada',
                'condonado' => 'Condonada',
                default => 'Estado desconocido'
            },
            'color' => match($this->estado) {
                'pendiente' => $this->estaVencida() ? 'red' : 'yellow',
                'pagado' => 'green',
                'atrasado' => 'red',
                'condonado' => 'blue',
                default => 'gray'
            }
        ];
    }
    
    private function getUrgencia(): string
    {
        if ($this->estado === 'pagado') {
            return 'ninguna';
        }
        
        $diasVencimiento = $this->getDiasVencimiento();
        
        return match (true) {
            $diasVencimiento > 0 => 'critica', // Ya vencida
            $diasVencimiento >= -3 => 'alta',   // Vence en 3 días o menos
            $diasVencimiento >= -7 => 'media',  // Vence en una semana
            default => 'baja'
        };
    }
} 