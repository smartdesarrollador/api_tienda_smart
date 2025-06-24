<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeguimientoPedido extends Model
{
    use HasFactory;

    protected $table = 'seguimiento_pedidos';

    protected $fillable = [
        'pedido_id',
        'estado_anterior',
        'estado_actual',
        'observaciones',
        'usuario_cambio_id',
        'latitud_seguimiento',
        'longitud_seguimiento',
        'tiempo_estimado_restante',
        'fecha_cambio',
        'notificado_cliente',
    ];

    protected $casts = [
        'pedido_id' => 'integer',
        'usuario_cambio_id' => 'integer',
        'latitud_seguimiento' => 'decimal:8',
        'longitud_seguimiento' => 'decimal:8',
        'tiempo_estimado_restante' => 'integer',
        'fecha_cambio' => 'datetime',
        'notificado_cliente' => 'boolean',
    ];

    // Relaciones
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function usuarioCambio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_cambio_id');
    }

    // Scopes
    public function scopePorEstado($query, string $estado)
    {
        return $query->where('estado_actual', $estado);
    }

    public function scopeNotificado($query)
    {
        return $query->where('notificado_cliente', true);
    }

    public function scopeSinNotificar($query)
    {
        return $query->where('notificado_cliente', false);
    }

    // Métodos auxiliares
    public static function crearSeguimiento(
        int $pedidoId, 
        string $estadoAnterior, 
        string $estadoActual, 
        ?string $observaciones = null, 
        ?int $usuarioId = null
    ): self {
        return self::create([
            'pedido_id' => $pedidoId,
            'estado_anterior' => $estadoAnterior,
            'estado_actual' => $estadoActual,
            'observaciones' => $observaciones,
            'usuario_cambio_id' => $usuarioId,
            'fecha_cambio' => now(),
        ]);
    }
} 