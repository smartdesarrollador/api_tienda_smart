<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetricasNegocio extends Model
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
        'ventas_totales' => 'decimal:2',
        'costo_envios' => 'decimal:2',
        'tiempo_promedio_entrega' => 'decimal:2',
        'ticket_promedio' => 'decimal:2',
        'productos_mas_vendidos' => 'array',
        'zonas_mas_activas' => 'array',
    ];
}
