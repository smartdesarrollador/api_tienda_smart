<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'tipo' => $this->tipo,
            'leido' => (bool) $this->leido,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Información temporal
            'tiempo_transcurrido' => $this->created_at->diffForHumans(),
            'es_reciente' => $this->created_at->isAfter(now()->subHours(24)),
            'dias_antiguedad' => $this->created_at->diffInDays(now()),
            
            // Información del tipo
            'tipo_detallado' => $this->getTipoDetallado(),
            'prioridad' => $this->getPrioridad(),
            
            // Estados
            'requiere_accion' => $this->requiereAccion(),
            'puede_eliminarse' => $this->puedeEliminarse(),
        ];
    }
    
    private function getTipoDetallado(): array
    {
        return [
            'codigo' => $this->tipo,
            'nombre' => match($this->tipo) {
                'pedido' => 'Pedido',
                'pago' => 'Pago',
                'promocion' => 'Promoción',
                'stock' => 'Stock',
                'sistema' => 'Sistema',
                'bienvenida' => 'Bienvenida',
                'recordatorio' => 'Recordatorio',
                default => 'General'
            },
            'icono' => match($this->tipo) {
                'pedido' => 'shopping-cart',
                'pago' => 'credit-card',
                'promocion' => 'tag',
                'stock' => 'package',
                'sistema' => 'settings',
                'bienvenida' => 'user-plus',
                'recordatorio' => 'clock',
                default => 'bell'
            },
            'color' => match($this->tipo) {
                'pedido' => 'blue',
                'pago' => 'green',
                'promocion' => 'purple',
                'stock' => 'orange',
                'sistema' => 'gray',
                'bienvenida' => 'indigo',
                'recordatorio' => 'yellow',
                default => 'gray'
            }
        ];
    }
    
    private function getPrioridad(): string
    {
        return match($this->tipo) {
            'pago', 'stock' => 'alta',
            'pedido', 'recordatorio' => 'media',
            'promocion', 'bienvenida', 'sistema' => 'baja',
            default => 'baja'
        };
    }
    
    private function requiereAccion(): bool
    {
        return in_array($this->tipo, ['pago', 'pedido', 'stock', 'recordatorio']) && !$this->leido;
    }
    
    private function puedeEliminarse(): bool
    {
        return $this->leido || $this->created_at->isBefore(now()->subDays(30));
    }
} 