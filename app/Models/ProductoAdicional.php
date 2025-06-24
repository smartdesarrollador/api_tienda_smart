<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductoAdicional extends Pivot
{
    protected $table = 'producto_adicional';

    protected $fillable = [
        'producto_id',
        'adicional_id',
        'obligatorio',
        'multiple',
        'cantidad_minima',
        'cantidad_maxima',
        'precio_personalizado',
        'incluido_gratis',
        'orden',
        'activo',
    ];

    protected $casts = [
        'producto_id' => 'integer',
        'adicional_id' => 'integer',
        'obligatorio' => 'boolean',
        'multiple' => 'boolean',
        'cantidad_minima' => 'integer',
        'cantidad_maxima' => 'integer',
        'precio_personalizado' => 'decimal:2',
        'incluido_gratis' => 'boolean',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];
} 