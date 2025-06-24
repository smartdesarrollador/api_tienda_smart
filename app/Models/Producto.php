<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'precio',
        'precio_oferta',
        'stock',
        'sku',
        'codigo_barras',
        'imagen_principal',
        'destacado',
        'activo',
        'categoria_id',
        'marca',
        'modelo',
        'garantia',
        'meta_title',
        'meta_description',
        'idioma',
        'moneda',
        'atributos_extra',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'precio_oferta' => 'decimal:2',
        'stock' => 'integer',
        'destacado' => 'boolean',
        'activo' => 'boolean',
        'atributos_extra' => 'array',
    ];

    // Relaciones
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function imagenes()
    {
        return $this->hasMany(ImagenProducto::class);
    }

    public function variaciones()
    {
        return $this->hasMany(VariacionProducto::class);
    }

    public function comentarios()
    {
        return $this->hasMany(Comentario::class);
    }

    public function favoritos()
    {
        return $this->hasMany(Favorito::class);
    }

    public function detallesPedidos()
    {
        return $this->hasMany(DetallePedido::class);
    }

    public function filtrosValores()
    {
        return $this->belongsToMany(FiltroValor::class, 'producto_filtro_valor');
    }

    public function adicionales()
    {
        return $this->belongsToMany(Adicional::class, 'producto_adicional')
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

    public function gruposAdicionales()
    {
        return $this->belongsToMany(GrupoAdicional::class, 'producto_grupo_adicional')
            ->withPivot([
                'obligatorio',
                'minimo_selecciones',
                'maximo_selecciones',
                'orden',
                'activo'
            ])
            ->withTimestamps();
    }

    public function carritoTemporal()
    {
        return $this->hasMany(CarritoTemporal::class);
    }

    public function movimientosInventario()
    {
        return $this->hasMany(InventarioMovimiento::class);
    }

    public function seoProducto()
    {
        return $this->hasOne(SeoProducto::class);
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDestacados($query)
    {
        return $query->where('destacado', true);
    }

    public function scopeConStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeEnOferta($query)
    {
        return $query->whereNotNull('precio_oferta');
    }

    // Accessors
    public function getPrecioFinalAttribute()
    {
        return $this->precio_oferta ?? $this->precio;
    }

    public function getEnOfertaAttribute(): bool
    {
        return !is_null($this->precio_oferta);
    }

    public function getDescuentoPorcentajeAttribute()
    {
        if (!$this->precio_oferta) {
            return 0;
        }
        
        return round((($this->precio - $this->precio_oferta) / $this->precio) * 100, 2);
    }

    // Métodos auxiliares para adicionales
    public function tieneAdicionales(): bool
    {
        return $this->adicionales()->exists() || $this->gruposAdicionales()->exists();
    }

    public function obtenerAdicionalesObligatorios()
    {
        return $this->adicionales()->wherePivot('obligatorio', true)->get();
    }

    public function obtenerGruposObligatorios()
    {
        return $this->gruposAdicionales()->wherePivot('obligatorio', true)->get();
    }

    // Métodos auxiliares para inventario
    public function actualizarStock(int $cantidad, string $motivo, ?string $referencia = null): void
    {
        $stockAnterior = $this->stock;
        $this->update(['stock' => $cantidad]);

        InventarioMovimiento::registrarMovimiento(
            $this->id,
            null,
            'ajuste',
            $cantidad,
            $stockAnterior,
            $motivo,
            $referencia
        );
    }

    public function reducirStock(int $cantidad, string $motivo = 'Venta', ?string $referencia = null): bool
    {
        if ($this->stock < $cantidad) {
            return false;
        }

        $stockAnterior = $this->stock;
        $this->update(['stock' => $this->stock - $cantidad]);

        InventarioMovimiento::registrarMovimiento(
            $this->id,
            null,
            'salida',
            $cantidad,
            $stockAnterior,
            $motivo,
            $referencia
        );

        return true;
    }

    public function incrementarStock(int $cantidad, string $motivo = 'Reposición', ?string $referencia = null): void
    {
        $stockAnterior = $this->stock;
        $this->update(['stock' => $this->stock + $cantidad]);

        InventarioMovimiento::registrarMovimiento(
            $this->id,
            null,
            'entrada',
            $cantidad,
            $stockAnterior,
            $motivo,
            $referencia
        );
    }
} 