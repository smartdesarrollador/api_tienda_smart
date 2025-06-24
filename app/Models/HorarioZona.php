<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioZona extends Model
{
    use HasFactory;

    protected $table = 'horarios_zona';

    protected $fillable = [
        'zona_reparto_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'activo',
        'dia_completo',
        'observaciones',
    ];

    protected $casts = [
        'zona_reparto_id' => 'integer',
        'activo' => 'boolean',
        'dia_completo' => 'boolean',
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

    public function scopePorDia($query, string $dia)
    {
        return $query->where('dia_semana', $dia);
    }

    public function scopeDiaCompleto($query)
    {
        return $query->where('dia_completo', true);
    }

    // Métodos auxiliares
    public function estaAbierto(\DateTime $fechaHora): bool
    {
        $diaSemana = strtolower($fechaHora->format('l'));
        $diasMap = [
            'monday' => 'lunes',
            'tuesday' => 'martes',
            'wednesday' => 'miercoles',
            'thursday' => 'jueves',
            'friday' => 'viernes',
            'saturday' => 'sabado',
            'sunday' => 'domingo',
        ];

        $diaEspanol = $diasMap[$diaSemana] ?? $diaSemana;

        if ($this->dia_semana !== $diaEspanol || !$this->activo) {
            return false;
        }

        if ($this->dia_completo) {
            return true;
        }

        $horaActual = $fechaHora->format('H:i:s');
        return $horaActual >= $this->hora_inicio && $horaActual <= $this->hora_fin;
    }
} 