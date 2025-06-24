<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcepcionZona extends Model
{
    use HasFactory;

    protected $table = 'excepciones_zona';

    protected $fillable = [
        'zona_reparto_id',
        'fecha_excepcion',
        'tipo',
        'hora_inicio',
        'hora_fin',
        'costo_especial',
        'tiempo_especial_min',
        'tiempo_especial_max',
        'motivo',
        'activo',
    ];

    protected $casts = [
        'zona_reparto_id' => 'integer',
        'fecha_excepcion' => 'date',
        'costo_especial' => 'decimal:2',
        'tiempo_especial_min' => 'integer',
        'tiempo_especial_max' => 'integer',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function zonaReparto(): BelongsTo
    {
        return $this->belongsTo(ZonaReparto::class);
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorFecha($query, string $fecha)
    {
        return $query->where('fecha_excepcion', $fecha);
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeNoDisponible($query)
    {
        return $query->where('tipo', 'no_disponible');
    }

    // Métodos auxiliares
    public function aplicaEnHora(\DateTime $fechaHora): bool
    {
        if ($this->fecha_excepcion->format('Y-m-d') !== $fechaHora->format('Y-m-d')) {
            return false;
        }

        if (!$this->activo) {
            return false;
        }

        if ($this->tipo === 'no_disponible') {
            return true;
        }

        if ($this->hora_inicio && $this->hora_fin) {
            $horaActual = $fechaHora->format('H:i:s');
            return $horaActual >= $this->hora_inicio && $horaActual <= $this->hora_fin;
        }

        return true;
    }
} 