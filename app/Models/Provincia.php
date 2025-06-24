<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provincia extends Model
{
    use HasFactory;

    protected $fillable = [
        'departamento_id',
        'nombre',
        'codigo',
        'codigo_inei',
        'activo',
    ];

    protected $casts = [
        'departamento_id' => 'integer',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function distritos(): HasMany
    {
        return $this->hasMany(Distrito::class);
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorDepartamento($query, int $departamentoId)
    {
        return $query->where('departamento_id', $departamentoId);
    }
} 