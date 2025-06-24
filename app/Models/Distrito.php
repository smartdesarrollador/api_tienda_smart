<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Distrito extends Model
{
    use HasFactory;

    protected $fillable = [
        'provincia_id',
        'nombre',
        'codigo',
        'codigo_inei',
        'codigo_postal',
        'latitud',
        'longitud',
        'activo',
        'disponible_delivery',
        'limites_geograficos',
    ];

    protected $casts = [
        'provincia_id' => 'integer',
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'activo' => 'boolean',
        'disponible_delivery' => 'boolean',
        'limites_geograficos' => 'array',
    ];

    // Relaciones
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    public function direcciones(): HasMany
    {
        return $this->hasMany(Direccion::class);
    }

    public function zonasReparto(): BelongsToMany
    {
        return $this->belongsToMany(ZonaReparto::class, 'zona_distrito')
            ->withPivot([
                'costo_envio_personalizado',
                'tiempo_adicional',
                'activo',
                'prioridad'
            ])
            ->withTimestamps();
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDisponibleDelivery($query)
    {
        return $query->where('disponible_delivery', true);
    }

    public function scopePorProvincia($query, int $provinciaId)
    {
        return $query->where('provincia_id', $provinciaId);
    }

    // Métodos auxiliares
    public function getDepartamentoAttribute()
    {
        return $this->provincia->departamento;
    }
} 