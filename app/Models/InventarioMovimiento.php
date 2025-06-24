<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    use HasFactory;

    protected $table = 'inventario_movimientos';

    protected $fillable = [
        'producto_id',
        'variacion_id',
        'tipo_movimiento',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'motivo',
        'referencia',
        'usuario_id',
    ];

    protected $casts = [
        'producto_id' => 'integer',
        'variacion_id' => 'integer',
        'cantidad' => 'integer',
        'stock_anterior' => 'integer',
        'stock_nuevo' => 'integer',
        'usuario_id' => 'integer',
    ];

    // Relaciones
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variacion(): BelongsTo
    {
        return $this->belongsTo(VariacionProducto::class, 'variacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo_movimiento', $tipo);
    }

    public function scopeEntradas($query)
    {
        return $query->where('tipo_movimiento', 'entrada');
    }

    public function scopeSalidas($query)
    {
        return $query->where('tipo_movimiento', 'salida');
    }

    public function scopeAjustes($query)
    {
        return $query->where('tipo_movimiento', 'ajuste');
    }

    public function scopePorProducto($query, int $productoId)
    {
        return $query->where('producto_id', $productoId);
    }

    // Métodos auxiliares
    public static function registrarMovimiento(
        int $productoId,
        ?int $variacionId,
        string $tipoMovimiento,
        int $cantidad,
        int $stockAnterior,
        string $motivo,
        ?string $referencia = null,
        ?int $usuarioId = null
    ): self {
        $stockNuevo = match ($tipoMovimiento) {
            'entrada', 'liberacion' => $stockAnterior + $cantidad,
            'salida', 'reserva' => $stockAnterior - $cantidad,
            'ajuste' => $cantidad, // En ajustes, cantidad es el stock final
            default => $stockAnterior,
        };

        return self::create([
            'producto_id' => $productoId,
            'variacion_id' => $variacionId,
            'tipo_movimiento' => $tipoMovimiento,
            'cantidad' => $tipoMovimiento === 'ajuste' ? $stockNuevo - $stockAnterior : $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $stockNuevo,
            'motivo' => $motivo,
            'referencia' => $referencia,
            'usuario_id' => $usuarioId,
        ]);
    }

    public function esEntrada(): bool
    {
        return in_array($this->tipo_movimiento, ['entrada', 'liberacion']);
    }

    public function esSalida(): bool
    {
        return in_array($this->tipo_movimiento, ['salida', 'reserva']);
    }
} 