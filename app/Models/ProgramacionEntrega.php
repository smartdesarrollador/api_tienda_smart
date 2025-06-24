<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramacionEntrega extends Model
{
    use HasFactory;

    protected $table = 'programacion_entregas';

    protected $fillable = [
        'pedido_id',
        'repartidor_id',
        'fecha_programada',
        'hora_inicio_ventana',
        'hora_fin_ventana',
        'estado',
        'orden_ruta',
        'notas_repartidor',
        'hora_salida',
        'hora_llegada',
        'motivo_fallo',
    ];

    protected $casts = [
        'pedido_id' => 'integer',
        'repartidor_id' => 'integer',
        'fecha_programada' => 'datetime',
        'orden_ruta' => 'integer',
        'hora_salida' => 'datetime',
        'hora_llegada' => 'datetime',
    ];

    // Relaciones
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repartidor_id');
    }

    // Scopes
    public function scopePorEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePorRepartidor($query, int $repartidorId)
    {
        return $query->where('repartidor_id', $repartidorId);
    }

    public function scopePorFecha($query, string $fecha)
    {
        return $query->whereDate('fecha_programada', $fecha);
    }

    public function scopeProgramado($query)
    {
        return $query->where('estado', 'programado');
    }

    public function scopeEnRuta($query)
    {
        return $query->where('estado', 'en_ruta');
    }

    public function scopeEntregado($query)
    {
        return $query->where('estado', 'entregado');
    }

    public function scopeOrdenRuta($query)
    {
        return $query->orderBy('orden_ruta');
    }

    // Métodos auxiliares
    public function estaEnVentanaDeEntrega(\DateTime $fechaHora): bool
    {
        if ($this->fecha_programada->format('Y-m-d') !== $fechaHora->format('Y-m-d')) {
            return false;
        }

        $horaActual = $fechaHora->format('H:i:s');
        return $horaActual >= $this->hora_inicio_ventana && 
               $horaActual <= $this->hora_fin_ventana;
    }

    public function calcularTiempoEntrega(): ?int
    {
        if (!$this->hora_salida || !$this->hora_llegada) {
            return null;
        }

        return $this->hora_llegada->diffInMinutes($this->hora_salida);
    }

    public function marcarComoEnRuta(): void
    {
        $this->update([
            'estado' => 'en_ruta',
            'hora_salida' => now(),
        ]);
    }

    public function marcarComoEntregado(): void
    {
        $this->update([
            'estado' => 'entregado',
            'hora_llegada' => now(),
        ]);
    }

    public function marcarComoFallido(string $motivo): void
    {
        $this->update([
            'estado' => 'fallido',
            'motivo_fallo' => $motivo,
        ]);
    }
} 