<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagenProducto extends Model
{
    use HasFactory;

    protected $table = 'imagenes_productos';

    protected $fillable = [
        'url',
        'alt',
        'orden',
        'principal',
        'producto_id',
        'variacion_id',
        'tipo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'principal' => 'boolean',
    ];

    // Relaciones
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variacion()
    {
        return $this->belongsTo(VariacionProducto::class);
    }

    // Scopes
    public function scopePrincipales($query)
    {
        return $query->where('principal', true);
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden');
    }
} 