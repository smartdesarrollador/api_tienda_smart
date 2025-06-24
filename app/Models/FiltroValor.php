<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiltroValor extends Model
{
    use HasFactory;

    protected $table = 'filtro_valor';

    protected $fillable = [
        'filtro_id',
        'valor',
        'codigo',
    ];

    // Relaciones
    public function filtro()
    {
        return $this->belongsTo(FiltroAvanzado::class, 'filtro_id');
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'producto_filtro_valor');
    }
} 