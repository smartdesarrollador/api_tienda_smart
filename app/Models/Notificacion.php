<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificaciones';

    protected $fillable = [
        'user_id',
        'titulo',
        'mensaje',
        'tipo',
        'leido',
    ];

    protected $casts = [
        'leido' => 'boolean',
    ];

    // Relaciones
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopeLeidas($query)
    {
        return $query->where('leido', true);
    }

    public function scopeNoLeidas($query)
    {
        return $query->where('leido', false);
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }
} 