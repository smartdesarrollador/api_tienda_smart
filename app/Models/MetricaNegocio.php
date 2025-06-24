<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetricaNegocio extends Model
{
    use HasFactory;

    protected $table = 'metricas_negocio';

    protected $fillable = [
        'fecha',
        'pedidos_totales',
        'pedidos_entregados',
        'pedidos_cancelados',
        'ventas_totales',
        'costo_envios',
        'nuevos_clientes',
        'clientes_recurrentes',
        'tiempo_promedio_entrega',
        'productos_vendidos',
        'ticket_promedio',
        'productos_mas_vendidos',
        'zonas_mas_activas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'pedidos_totales' => 'integer',
        'pedidos_entregados' => 'integer',
        'pedidos_cancelados' => 'integer',
        'ventas_totales' => 'decimal:2',
        'costo_envios' => 'decimal:2',
        'nuevos_clientes' => 'integer',
        'clientes_recurrentes' => 'integer',
        'tiempo_promedio_entrega' => 'decimal:2',
        'productos_vendidos' => 'integer',
        'ticket_promedio' => 'decimal:2',
        'productos_mas_vendidos' => 'array',
        'zonas_mas_activas' => 'array',
    ];

    // Scopes
    public function scopePorFecha($query, string $fecha)
    {
        return $query->where('fecha', $fecha);
    }

    public function scopeEntreFechas($query, string $fechaInicio, string $fechaFin)
    {
        return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
    }

    public function scopeUltimosMeses($query, int $meses = 6)
    {
        return $query->where('fecha', '>=', now()->subMonths($meses));
    }

    // Métodos auxiliares
    public function getTasaConversionAttribute(): float
    {
        if ($this->pedidos_totales === 0) {
            return 0;
        }

        return ($this->pedidos_entregados / $this->pedidos_totales) * 100;
    }

    public function getTasaCancelacionAttribute(): float
    {
        if ($this->pedidos_totales === 0) {
            return 0;
        }

        return ($this->pedidos_cancelados / $this->pedidos_totales) * 100;
    }

    public function getVentasNetasAttribute(): float
    {
        return $this->ventas_totales - $this->costo_envios;
    }

    public static function generarMetricasDelDia(string $fecha): self
    {
        // Este método podría generar las métricas del día especificado
        // calculando desde los pedidos, productos vendidos, etc.
        
        $pedidos = Pedido::whereDate('created_at', $fecha);
        $pedidosEntregados = (clone $pedidos)->where('estado', 'entregado');
        $pedidosCancelados = (clone $pedidos)->where('estado', 'cancelado');

        return self::updateOrCreate(
            ['fecha' => $fecha],
            [
                'pedidos_totales' => $pedidos->count(),
                'pedidos_entregados' => $pedidosEntregados->count(),
                'pedidos_cancelados' => $pedidosCancelados->count(),
                'ventas_totales' => $pedidosEntregados->sum('total'),
                'costo_envios' => $pedidosEntregados->sum('costo_envio'),
                'ticket_promedio' => $pedidosEntregados->avg('total') ?? 0,
                // ... más cálculos según necesidades
            ]
        );
    }
} 