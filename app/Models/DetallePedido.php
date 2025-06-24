<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetallePedido extends Model
{
    use HasFactory;

    protected $table = 'detalle_pedidos';

    protected $fillable = [
        'pedido_id',
        'producto_id',
        'variacion_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'descuento',
        'impuesto',
        'moneda',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'impuesto' => 'decimal:2',
    ];

    // Relaciones
    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variacion()
    {
        return $this->belongsTo(VariacionProducto::class);
    }

    public function detalleAdicionales()
    {
        return $this->hasMany(DetalleAdicional::class);
    }

    // Accessors
    public function getTotalAttribute()
    {
        return $this->subtotal + ($this->impuesto ?? 0) - ($this->descuento ?? 0);
    }

    public function getTotalConAdicionalesAttribute(): float
    {
        $totalBase = $this->total;
        $totalAdicionales = $this->detalleAdicionales->sum('subtotal');
        
        return $totalBase + $totalAdicionales;
    }

    // Métodos auxiliares para adicionales
    public function agregarAdicional(int $adicionalId, int $cantidad, float $precioUnitario, ?string $observaciones = null): DetalleAdicional
    {
        return $this->detalleAdicionales()->create([
            'adicional_id' => $adicionalId,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal' => $cantidad * $precioUnitario,
            'observaciones' => $observaciones,
        ]);
    }

    public function obtenerAdicionalesPorTipo(string $tipo)
    {
        return $this->detalleAdicionales()
            ->whereHas('adicional', function ($query) use ($tipo) {
                $query->where('tipo', $tipo);
            })
            ->get();
    }

    public function tieneAdicionales(): bool
    {
        return $this->detalleAdicionales()->exists();
    }

    public function calcularTotalFinal(): float
    {
        // Recalcular el total incluyendo adicionales
        return $this->total_con_adicionales;
    }
} 