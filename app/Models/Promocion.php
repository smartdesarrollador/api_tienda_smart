<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    use HasFactory;

    protected $table = 'promociones';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'tipo',
        'descuento_porcentaje',
        'descuento_monto',
        'compra_minima',
        'fecha_inicio',
        'fecha_fin',
        'activo',
        'productos_incluidos',
        'categorias_incluidas',
        'zonas_aplicables',
        'limite_uso_total',
        'limite_uso_cliente',
        'usos_actuales',
        'imagen',
    ];

    protected $casts = [
        'descuento_porcentaje' => 'decimal:2',
        'descuento_monto' => 'decimal:2',
        'compra_minima' => 'decimal:2',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'activo' => 'boolean',
        'productos_incluidos' => 'array',
        'categorias_incluidas' => 'array',
        'zonas_aplicables' => 'array',
        'limite_uso_total' => 'integer',
        'limite_uso_cliente' => 'integer',
        'usos_actuales' => 'integer',
    ];

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeVigente($query)
    {
        $now = now();
        return $query->where('fecha_inicio', '<=', $now)
            ->where('fecha_fin', '>=', $now);
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeConUsosDisponibles($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('limite_uso_total')
              ->orWhereRaw('usos_actuales < limite_uso_total');
        });
    }

    // Métodos auxiliares
    public function estaVigente(): bool
    {
        $now = now();
        return $this->activo && 
               $this->fecha_inicio <= $now && 
               $this->fecha_fin >= $now;
    }

    public function tieneUsosDisponibles(): bool
    {
        return $this->limite_uso_total === null || 
               $this->usos_actuales < $this->limite_uso_total;
    }

    public function aplicaAProducto(int $productoId): bool
    {
        if (empty($this->productos_incluidos)) {
            return true; // Aplica a todos los productos
        }

        return in_array($productoId, $this->productos_incluidos);
    }

    public function aplicaACategoria(int $categoriaId): bool
    {
        if (empty($this->categorias_incluidas)) {
            return true; // Aplica a todas las categorías
        }

        return in_array($categoriaId, $this->categorias_incluidas);
    }

    public function aplicaAZona(int $zonaId): bool
    {
        if (empty($this->zonas_aplicables)) {
            return true; // Aplica a todas las zonas
        }

        return in_array($zonaId, $this->zonas_aplicables);
    }

    public function calcularDescuento(float $monto): float
    {
        if ($this->compra_minima && $monto < $this->compra_minima) {
            return 0;
        }

        return match ($this->tipo) {
            'descuento_producto', 'descuento_categoria' => $this->descuento_porcentaje 
                ? $monto * ($this->descuento_porcentaje / 100)
                : $this->descuento_monto ?? 0,
            'envio_gratis' => 0, // El descuento se aplica al envío, no al monto
            default => 0,
        };
    }

    public function incrementarUsos(): void
    {
        $this->increment('usos_actuales');
    }
} 