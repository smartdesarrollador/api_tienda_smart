<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZonaDistrito extends Model
{
    protected $table = 'zona_distrito';

    protected $fillable = [
        'zona_reparto_id',
        'distrito_id',
        'costo_envio_personalizado',
        'tiempo_adicional',
        'activo',
        'prioridad',
    ];

    protected $casts = [
        'zona_reparto_id' => 'integer',
        'distrito_id' => 'integer',
        'costo_envio_personalizado' => 'decimal:2',
        'tiempo_adicional' => 'integer',
        'activo' => 'boolean',
        'prioridad' => 'integer',
    ];

    /**
     * Relación con ZonaReparto
     */
    public function zonaReparto(): BelongsTo
    {
        return $this->belongsTo(ZonaReparto::class, 'zona_reparto_id');
    }

    /**
     * Relación con Distrito
     */
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }
} 