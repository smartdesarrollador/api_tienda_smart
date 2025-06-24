<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Atributo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'slug',
        'tipo',
        'filtrable',
        'visible',
    ];

    protected $casts = [
        'filtrable' => 'boolean',
        'visible' => 'boolean',
    ];

    // Relaciones
    public function valores()
    {
        return $this->hasMany(ValorAtributo::class);
    }

    // Scopes
    public function scopeFiltrables($query)
    {
        return $query->where('filtrable', true);
    }

    public function scopeVisibles($query)
    {
        return $query->where('visible', true);
    }
} 