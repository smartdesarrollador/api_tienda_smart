<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariacionProducto extends Model
{
    use HasFactory;

    protected $table = 'variaciones_productos';

    protected $fillable = [
        'producto_id',
        'sku',
        'precio',
        'precio_oferta',
        'stock',
        'activo',
        'atributos',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'precio_oferta' => 'decimal:2',
        'stock' => 'integer',
        'activo' => 'boolean',
        'atributos' => 'array',
    ];

    // Relaciones
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function imagenes()
    {
        return $this->hasMany(ImagenProducto::class, 'variacion_id');
    }

    public function valoresAtributos()
    {
        return $this->belongsToMany(ValorAtributo::class, 'variacion_valor', 'variacion_id', 'valor_atributo_id');
    }

    public function detallesPedidos()
    {
        return $this->hasMany(DetallePedido::class, 'variacion_id');
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeConStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    // Accessors
    public function getPrecioFinalAttribute()
    {
        return $this->precio_oferta ?? $this->precio;
    }
} 