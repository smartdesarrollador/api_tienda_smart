<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuotaCredito extends Model
{
    use HasFactory;

    protected $table = 'cuotas_credito';

    protected $fillable = [
        'pedido_id',
        'numero_cuota',
        'monto_cuota',
        'interes',
        'mora',
        'fecha_vencimiento',
        'fecha_pago',
        'estado',
        'moneda',
    ];

    protected $casts = [
        'numero_cuota' => 'integer',
        'monto_cuota' => 'decimal:2',
        'interes' => 'decimal:2',
        'mora' => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'fecha_pago' => 'date',
    ];

    // Relaciones
    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    // Scopes
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopePagadas($query)
    {
        return $query->where('estado', 'pagado');
    }

    public function scopeAtrasadas($query)
    {
        return $query->where('estado', 'atrasado');
    }

    public function scopeVencidas($query)
    {
        return $query->where('fecha_vencimiento', '<', now())
            ->where('estado', 'pendiente');
    }

    // Accessors
    public function getMontoTotalAttribute()
    {
        return $this->monto_cuota + ($this->interes ?? 0) + ($this->mora ?? 0);
    }

    public function getEstaVencidaAttribute(): bool
    {
        return $this->fecha_vencimiento < now() && $this->estado === 'pendiente';
    }
} 