<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorito extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'producto_id',
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
} 