<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GrupoAdicional extends Model
{
    use HasFactory;

    protected $table = 'grupos_adicionales';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'obligatorio',
        'multiple_seleccion',
        'minimo_selecciones',
        'maximo_selecciones',
        'orden',
        'activo',
    ];

    protected $casts = [
        'obligatorio' => 'boolean',
        'multiple_seleccion' => 'boolean',
        'minimo_selecciones' => 'integer',
        'maximo_selecciones' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function adicionales(): BelongsToMany
    {
        return $this->belongsToMany(Adicional::class, 'adicional_grupo')
            ->withPivot('orden')
            ->withTimestamps();
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_grupo_adicional')
            ->withPivot([
                'obligatorio',
                'minimo_selecciones',
                'maximo_selecciones',
                'orden',
                'activo'
            ])
            ->withTimestamps();
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeObligatorio($query)
    {
        return $query->where('obligatorio', true);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden');
    }
} 