<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValorAtributo extends Model
{
    use HasFactory;

    protected $table = 'valores_atributo';

    protected $fillable = [
        'atributo_id',
        'valor',
        'codigo',
        'imagen',
    ];

    // Relaciones
    public function atributo()
    {
        return $this->belongsTo(Atributo::class);
    }

    public function variaciones()
    {
        return $this->belongsToMany(VariacionProducto::class, 'variacion_valor', 'valor_atributo_id', 'variacion_id');
    }
} 