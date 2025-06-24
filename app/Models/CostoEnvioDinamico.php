<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostoEnvioDinamico extends Model
{
    use HasFactory;

    protected $table = 'costos_envio_dinamicos';

    protected $fillable = [
        'zona_reparto_id',
        'distancia_desde_km',
        'distancia_hasta_km',
        'costo_envio',
        'tiempo_adicional',
        'activo',
    ];

    protected $casts = [
        'zona_reparto_id' => 'integer',
        'distancia_desde_km' => 'decimal:2',
        'distancia_hasta_km' => 'decimal:2',
        'costo_envio' => 'decimal:2',
        'tiempo_adicional' => 'decimal:2',
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

    public function scopePorDistancia($query, float $distancia)
    {
        return $query->where('distancia_desde_km', '<=', $distancia)
            ->where('distancia_hasta_km', '>', $distancia);
    }

    // Métodos auxiliares
    public function incluyeDistancia(float $distancia): bool
    {
        return $distancia >= $this->distancia_desde_km && $distancia < $this->distancia_hasta_km;
    }
} 