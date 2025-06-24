<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adicional extends Model
{
    use HasFactory;

    protected $table = 'adicionales';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'precio',
        'imagen',
        'icono',
        'tipo',
        'disponible',
        'activo',
        'stock',
        'tiempo_preparacion',
        'calorias',
        'informacion_nutricional',
        'alergenos',
        'vegetariano',
        'vegano',
        'orden',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'disponible' => 'boolean',
        'activo' => 'boolean',
        'stock' => 'integer',
        'tiempo_preparacion' => 'integer',
        'calorias' => 'decimal:2',
        'informacion_nutricional' => 'array',
        'alergenos' => 'array',
        'vegetariano' => 'boolean',
        'vegano' => 'boolean',
        'orden' => 'integer',
    ];

    // Relaciones
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_adicional')
            ->withPivot([
                'obligatorio',
                'multiple',
                'cantidad_minima',
                'cantidad_maxima',
                'precio_personalizado',
                'incluido_gratis',
                'orden',
                'activo'
            ])
            ->withTimestamps();
    }

    public function gruposAdicionales(): BelongsToMany
    {
        return $this->belongsToMany(GrupoAdicional::class, 'adicional_grupo')
            ->withPivot('orden')
            ->withTimestamps();
    }

    public function detalleAdicionales(): HasMany
    {
        return $this->hasMany(DetalleAdicional::class);
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDisponible($query)
    {
        return $query->where('disponible', true);
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeVegetariano($query)
    {
        return $query->where('vegetariano', true);
    }

    public function scopeVegano($query)
    {
        return $query->where('vegano', true);
    }

    // Métodos auxiliares
    public function tieneStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }

    public function esAlergenoLibre(array $alergenosEvitar): bool
    {
        if (!$this->alergenos) {
            return true;
        }

        return empty(array_intersect($this->alergenos, $alergenosEvitar));
    }
} 