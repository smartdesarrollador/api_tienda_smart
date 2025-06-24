<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $fillable = [
        'pedido_id',
        'metodo_pago_id',
        'monto',
        'comision',
        'numero_cuota',
        'fecha_pago',
        'estado',
        'metodo',
        'referencia',
        'moneda',
        'respuesta_proveedor',
        'codigo_autorizacion',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'comision' => 'decimal:2',
        'numero_cuota' => 'integer',
        'fecha_pago' => 'date',
        'respuesta_proveedor' => 'array',
    ];

    // Relaciones
    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    // Scopes
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePagados($query)
    {
        return $query->where('estado', 'pagado');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeAtrasados($query)
    {
        return $query->where('estado', 'atrasado');
    }

    public function scopePorMetodoPago($query, $metodoPagoId)
    {
        return $query->where('metodo_pago_id', $metodoPagoId);
    }

    public function scopeFallidos($query)
    {
        return $query->where('estado', 'fallido');
    }

    public function scopeReembolsados($query)
    {
        return $query->where('estado', 'reembolsado');
    }

    // Accessors
    public function getMontoConComisionAttribute()
    {
        return $this->monto + ($this->comision ?? 0);
    }

    public function getEsPagadoAttribute(): bool
    {
        return $this->estado === 'pagado';
    }

    public function getEsPendienteAttribute(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function getEsFallidoAttribute(): bool
    {
        return $this->estado === 'fallido';
    }

    // Métodos de utilidad
    public function marcarComoPagado(string $codigoAutorizacion = null, array $respuestaProveedor = null): bool
    {
        $this->estado = 'pagado';
        if ($codigoAutorizacion) {
            $this->codigo_autorizacion = $codigoAutorizacion;
        }
        if ($respuestaProveedor) {
            $this->respuesta_proveedor = $respuestaProveedor;
        }
        
        return $this->save();
    }

    public function marcarComoFallido(string $motivo = null, array $respuestaProveedor = null): bool
    {
        $this->estado = 'fallido';
        if ($motivo) {
            $this->observaciones = $motivo;
        }
        if ($respuestaProveedor) {
            $this->respuesta_proveedor = $respuestaProveedor;
        }
        
        return $this->save();
    }

    public function calcularComisionPorcentual(): float
    {
        if (!$this->comision || !$this->monto) {
            return 0;
        }
        
        return round(($this->comision / $this->monto) * 100, 2);
    }
} 