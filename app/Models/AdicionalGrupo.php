<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AdicionalGrupo extends Pivot
{
    protected $table = 'adicional_grupo';

    protected $fillable = [
        'adicional_id',
        'grupo_adicional_id',
        'orden',
    ];

    protected $casts = [
        'adicional_id' => 'integer',
        'grupo_adicional_id' => 'integer',
        'orden' => 'integer',
    ];
} 