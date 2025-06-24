<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cupon extends Model
{
    use HasFactory;

    protected $table = 'cupones';

    protected $fillable = [
        'codigo',
        'descuento',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'limite_uso',
        'usos',
        'activo',
        'descripcion',
    ];

    protected $casts = [
        'descuento' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'limite_uso' => 'integer',
        'usos' => 'integer',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'cupon_usuario')
            ->withPivot('usado')
            ->withTimestamps();
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeVigentes($query)
    {
        return $query->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now());
    }

    public function scopeDisponibles($query)
    {
        return $query->whereRaw('(limite_uso IS NULL OR usos < limite_uso)');
    }

    // Accessors
    public function getEsVigenteAttribute(): bool
    {
        return $this->fecha_inicio <= now() && $this->fecha_fin >= now();
    }

    public function getTieneUsosDisponiblesAttribute(): bool
    {
        return is_null($this->limite_uso) || $this->usos < $this->limite_uso;
    }
} 