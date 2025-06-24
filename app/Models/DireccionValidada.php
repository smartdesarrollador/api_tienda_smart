<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DireccionValidada extends Model
{
    use HasFactory;

    protected $table = 'direcciones_validadas';

    protected $fillable = [
        'direccion_id',
        'zona_reparto_id',
        'latitud',
        'longitud',
        'distancia_tienda_km',
        'en_zona_cobertura',
        'costo_envio_calculado',
        'tiempo_entrega_estimado',
        'fecha_ultima_validacion',
        'observaciones_validacion',
    ];

    protected $casts = [
        'direccion_id' => 'integer',
        'zona_reparto_id' => 'integer',
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'distancia_tienda_km' => 'decimal:2',
        'en_zona_cobertura' => 'boolean',
        'costo_envio_calculado' => 'decimal:2',
        'tiempo_entrega_estimado' => 'integer',
        'fecha_ultima_validacion' => 'datetime',
    ];

    // Relaciones
    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class);
    }

    public function zonaReparto(): BelongsTo
    {
        return $this->belongsTo(ZonaReparto::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    // Scopes
    public function scopeEnCobertura($query)
    {
        return $query->where('en_zona_cobertura', true);
    }

    public function scopePorZona($query, int $zonaId)
    {
        return $query->where('zona_reparto_id', $zonaId);
    }

    // Métodos auxiliares
    public function esValidaParaEntrega(): bool
    {
        return $this->en_zona_cobertura && 
               $this->zona_reparto_id !== null && 
               $this->costo_envio_calculado !== null;
    }
} 