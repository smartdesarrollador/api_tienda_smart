<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categoria extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'imagen',
        'activo',
        'orden',
        'categoria_padre_id',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    // Relaciones
    public function categoriaPadre()
    {
        return $this->belongsTo(Categoria::class, 'categoria_padre_id');
    }

    public function subcategorias()
    {
        return $this->hasMany(Categoria::class, 'categoria_padre_id');
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopePrincipales($query)
    {
        return $query->whereNull('categoria_padre_id');
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden');
    }
} 