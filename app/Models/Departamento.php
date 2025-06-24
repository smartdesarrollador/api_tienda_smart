<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'codigo_inei',
        'pais',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relaciones
    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class);
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorPais($query, string $pais)
    {
        return $query->where('pais', $pais);
    }
} 