<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comentario extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'producto_id',
        'comentario',
        'calificacion',
        'aprobado',
        'titulo',
        'respuesta_admin',
    ];

    protected $casts = [
        'calificacion' => 'integer',
        'aprobado' => 'boolean',
    ];

    // Relaciones
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    // Scopes
    public function scopeAprobados($query)
    {
        return $query->where('aprobado', true);
    }

    public function scopeConCalificacion($query)
    {
        return $query->whereNotNull('calificacion');
    }

    public function scopePorCalificacion($query, $calificacion)
    {
        return $query->where('calificacion', $calificacion);
    }
} 