<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleAdicional extends Model
{
    use HasFactory;

    protected $table = 'detalle_adicionales';

    protected $fillable = [
        'detalle_pedido_id',
        'adicional_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'observaciones',
    ];

    protected $casts = [
        'detalle_pedido_id' => 'integer',
        'adicional_id' => 'integer',
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Relaciones
    public function detallePedido(): BelongsTo
    {
        return $this->belongsTo(DetallePedido::class);
    }

    public function adicional(): BelongsTo
    {
        return $this->belongsTo(Adicional::class);
    }

    // Métodos auxiliares
    protected static function booted()
    {
        static::saving(function ($detalleAdicional) {
            $detalleAdicional->subtotal = $detalleAdicional->cantidad * $detalleAdicional->precio_unitario;
        });
    }
} 