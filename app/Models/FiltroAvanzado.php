<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiltroAvanzado extends Model
{
    use HasFactory;

    protected $table = 'filtros_avanzados';

    protected $fillable = [
        'nombre',
        'slug',
        'tipo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relaciones
    public function valores()
    {
        return $this->hasMany(FiltroValor::class, 'filtro_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }
} 