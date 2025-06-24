<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ZonaReparto extends Model
{
    use HasFactory;

    protected $table = 'zonas_reparto';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'costo_envio',
        'costo_envio_adicional',
        'tiempo_entrega_min',
        'tiempo_entrega_max',
        'pedido_minimo',
        'radio_cobertura_km',
        'coordenadas_centro',
        'poligono_cobertura',
        'activo',
        'disponible_24h',
        'orden',
        'color_mapa',
        'observaciones',
    ];

    protected $casts = [
        'costo_envio' => 'decimal:2',
        'costo_envio_adicional' => 'decimal:2',
        'pedido_minimo' => 'decimal:2',
        'radio_cobertura_km' => 'decimal:2',
        'poligono_cobertura' => 'array',
        'activo' => 'boolean',
        'disponible_24h' => 'boolean',
        'orden' => 'integer',
        'tiempo_entrega_min' => 'integer',
        'tiempo_entrega_max' => 'integer',
    ];

    // Relaciones
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function direccionesValidadas(): HasMany
    {
        return $this->hasMany(DireccionValidada::class);
    }

    public function distritos(): BelongsToMany
    {
        return $this->belongsToMany(Distrito::class, 'zona_distrito')
            ->withPivot([
                'costo_envio_personalizado',
                'tiempo_adicional',
                'activo',
                'prioridad'
            ])
            ->withTimestamps();
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioZona::class);
    }

    public function costosEnvioDinamicos(): HasMany
    {
        return $this->hasMany(CostoEnvioDinamico::class);
    }

    public function excepciones(): HasMany
    {
        return $this->hasMany(ExcepcionZona::class);
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDisponible24h($query)
    {
        return $query->where('disponible_24h', true);
    }

    // Métodos auxiliares
    public function estaDisponibleEnFecha(\DateTime $fecha): bool
    {
        // Verificar si hay excepciones para esta fecha
        $excepcion = $this->excepciones()
            ->where('fecha_excepcion', $fecha->format('Y-m-d'))
            ->where('tipo', 'no_disponible')
            ->where('activo', true)
            ->first();

        return !$excepcion;
    }

    public function calcularCostoEnvio(float $distanciaKm): float
    {
        $costo = $this->costosEnvioDinamicos()
            ->where('distancia_desde_km', '<=', $distanciaKm)
            ->where('distancia_hasta_km', '>', $distanciaKm)
            ->where('activo', true)
            ->first();

        return $costo ? $costo->costo_envio : $this->costo_envio;
    }
} 