<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogAuditoria extends Model
{
    use HasFactory;

    protected $table = 'logs_auditoria';

    protected $fillable = [
        'user_id',
        'accion',
        'tabla_afectada',
        'registro_id',
        'datos_anteriores',
        'datos_nuevos',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
    ];

    // Relaciones
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopePorAccion($query, $accion)
    {
        return $query->where('accion', $accion);
    }

    public function scopePorTabla($query, $tabla)
    {
        return $query->where('tabla_afectada', $tabla);
    }

    public function scopePorUsuario($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
} 