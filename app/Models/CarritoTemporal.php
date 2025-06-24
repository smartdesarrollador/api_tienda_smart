<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarritoTemporal extends Model
{
    use HasFactory;

    protected $table = 'carrito_temporal';

    protected $fillable = [
        'user_id',
        'session_id',
        'producto_id',
        'variacion_id',
        'cantidad',
        'precio_unitario',
        'adicionales_seleccionados',
        'observaciones',
        'fecha_expiracion',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'producto_id' => 'integer',
        'variacion_id' => 'integer',
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'adicionales_seleccionados' => 'array',
        'fecha_expiracion' => 'datetime',
    ];

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variacion(): BelongsTo
    {
        return $this->belongsTo(VariacionProducto::class, 'variacion_id');
    }

    // Scopes
    public function scopePorUsuario($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePorSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeNoExpirado($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('fecha_expiracion')
              ->orWhere('fecha_expiracion', '>', now());
        });
    }

    public function scopeExpirado($query)
    {
        return $query->where('fecha_expiracion', '<=', now());
    }

    // Métodos auxiliares
    public function calcularSubtotal(): float
    {
        $subtotal = $this->cantidad * $this->precio_unitario;
        
        // Agregar costo de adicionales
        if ($this->adicionales_seleccionados) {
            foreach ($this->adicionales_seleccionados as $adicionalId => $cantidad) {
                $adicional = Adicional::find($adicionalId);
                if ($adicional) {
                    $subtotal += $cantidad * $adicional->precio;
                }
            }
        }
        
        return $subtotal;
    }

    public function estaExpirado(): bool
    {
        return $this->fecha_expiracion && $this->fecha_expiracion <= now();
    }

    public static function limpiarExpirados(): int
    {
        return self::expirado()->delete();
    }
} 