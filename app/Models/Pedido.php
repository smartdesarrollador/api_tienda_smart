<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pedido extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'numero_pedido',
        'metodo_pago_id',
        'zona_reparto_id',
        'direccion_validada_id',
        'subtotal',
        'descuento',
        'igv',
        'costo_envio',
        'total',
        'estado',
        'tipo_pago',
        'tipo_entrega',
        'cuotas',
        'monto_cuota',
        'interes_total',
        'descuento_total',
        'observaciones',
        'codigo_rastreo',
        'moneda',
        'canal_venta',
        'tiempo_entrega_estimado',
        'fecha_entrega_programada',
        'fecha_entrega_real',
        'direccion_entrega',
        'telefono_entrega',
        'referencia_entrega',
        'latitud_entrega',
        'longitud_entrega',
        'repartidor_id',
        'datos_envio',
        'metodo_envio',
        'datos_cliente',
        'cupon_codigo',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'metodo_pago_id' => 'integer',
        'zona_reparto_id' => 'integer',
        'direccion_validada_id' => 'integer',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'igv' => 'decimal:2',
        'costo_envio' => 'decimal:2',
        'total' => 'decimal:2',
        'cuotas' => 'integer',
        'monto_cuota' => 'decimal:2',
        'interes_total' => 'decimal:2',
        'descuento_total' => 'decimal:2',
        'tiempo_entrega_estimado' => 'integer',
        'fecha_entrega_programada' => 'datetime',
        'fecha_entrega_real' => 'datetime',
        'latitud_entrega' => 'decimal:8',
        'longitud_entrega' => 'decimal:8',
        'repartidor_id' => 'integer',
        'datos_envio' => 'array',
        'metodo_envio' => 'array',
        'datos_cliente' => 'array',
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }

    public function zonaReparto(): BelongsTo
    {
        return $this->belongsTo(ZonaReparto::class);
    }

    public function direccionValidada(): BelongsTo
    {
        return $this->belongsTo(DireccionValidada::class);
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repartidor_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePedido::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoPedido::class);
    }

    public function programacionEntrega(): HasOne
    {
        return $this->hasOne(ProgramacionEntrega::class);
    }

    public function cuotasCredito(): HasMany
    {
        return $this->hasMany(CuotaCredito::class);
    }

    // Scopes
    public function scopePorEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePorUsuario($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePorZona($query, int $zonaId)
    {
        return $query->where('zona_reparto_id', $zonaId);
    }

    public function scopePorRepartidor($query, int $repartidorId)
    {
        return $query->where('repartidor_id', $repartidorId);
    }

    public function scopeDelivery($query)
    {
        return $query->where('tipo_entrega', 'delivery');
    }

    public function scopeRecojo($query)
    {
        return $query->where('tipo_entrega', 'recojo_tienda');
    }

    // Métodos auxiliares
    public function calcularTotal(): void
    {
        $this->total = $this->subtotal + $this->costo_envio - ($this->descuento_total ?? 0);
        $this->save();
    }

    public function cambiarEstado(string $nuevoEstado, ?string $observaciones = null, ?int $usuarioId = null): void
    {
        $estadoAnterior = $this->estado;
        $this->update(['estado' => $nuevoEstado]);

        // Crear registro de seguimiento
        SeguimientoPedido::crearSeguimiento(
            $this->id,
            $estadoAnterior,
            $nuevoEstado,
            $observaciones,
            $usuarioId
        );
    }

    public function estaEntregado(): bool
    {
        return $this->estado === 'entregado';
    }

    public function estaCancelado(): bool
    {
        return $this->estado === 'cancelado';
    }

    public function puedeSerCancelado(): bool
    {
        return in_array($this->estado, ['pendiente', 'confirmado']);
    }

    public function obtenerUltimoSeguimiento(): ?SeguimientoPedido
    {
        return $this->seguimientos()->latest('fecha_cambio')->first();
    }

    public function generarCodigoRastreo(): string
    {
        if (!$this->codigo_rastreo) {
            $codigo = 'PED-' . str_pad((string)$this->id, 6, '0', STR_PAD_LEFT) . '-' . date('Y');
            $this->update(['codigo_rastreo' => $codigo]);
            return $codigo;
        }

        return $this->codigo_rastreo;
    }

    // Propiedades calculadas
    public function getTotalConDescuentoAttribute(): float
    {
        return $this->total - ($this->descuento_total ?? 0);
    }

    public function getEsCreditoAttribute(): bool
    {
        return $this->tipo_pago === 'credito';
    }

    public function puedeCanselar(): bool
    {
        return in_array($this->estado, ['pendiente', 'aprobado']);
    }

    public function getNumeroPedidoAttribute(): string
    {
        return 'PED-' . str_pad((string)$this->id, 6, '0', STR_PAD_LEFT);
    }
} 