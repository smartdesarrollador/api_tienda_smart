<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductoGrupoAdicional extends Pivot
{
    protected $table = 'producto_grupo_adicional';

    protected $fillable = [
        'producto_id',
        'grupo_adicional_id',
        'obligatorio',
        'minimo_selecciones',
        'maximo_selecciones',
        'orden',
        'activo',
    ];

    protected $casts = [
        'producto_id' => 'integer',
        'grupo_adicional_id' => 'integer',
        'obligatorio' => 'boolean',
        'minimo_selecciones' => 'integer',
        'maximo_selecciones' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];
} 