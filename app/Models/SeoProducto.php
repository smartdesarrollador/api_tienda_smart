<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoProducto extends Model
{
    use HasFactory;

    protected $table = 'seo_productos';

    protected $fillable = [
        'producto_id',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'schema_markup',
        'og_title',
        'og_description',
        'og_image',
    ];

    protected $casts = [
        'producto_id' => 'integer',
        'schema_markup' => 'array',
    ];

    // Relaciones
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    // Métodos auxiliares
    public function generarSchemaMarkup(): array
    {
        $producto = $this->producto;
        
        if (!$producto) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $producto->nombre,
            'description' => $producto->descripcion,
            'image' => $producto->imagen_principal,
            'sku' => $producto->sku,
            'brand' => [
                '@type' => 'Brand',
                'name' => $producto->marca ?? 'Mi Tienda',
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => $producto->precio_oferta ?? $producto->precio,
                'priceCurrency' => $producto->moneda,
                'availability' => $producto->stock > 0 
                    ? 'https://schema.org/InStock' 
                    : 'https://schema.org/OutOfStock',
                'url' => url('/productos/' . $producto->slug),
            ],
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => $producto->comentarios()->avg('calificacion') ?? 0,
                'reviewCount' => $producto->comentarios()->count(),
            ],
        ];
    }

    public function actualizarSchemaMarkup(): void
    {
        $this->update([
            'schema_markup' => $this->generarSchemaMarkup(),
        ]);
    }

    public function getMetaTitleCompleto(): string
    {
        return $this->meta_title ?? $this->producto?->nombre ?? '';
    }

    public function getMetaDescriptionCompleta(): string
    {
        if ($this->meta_description) {
            return $this->meta_description;
        }

        // Generar descripción automática si no existe
        $producto = $this->producto;
        if ($producto) {
            $descripcion = substr(strip_tags($producto->descripcion), 0, 155);
            return $descripcion . '...';
        }

        return '';
    }

    public function getOpenGraphData(): array
    {
        $producto = $this->producto;
        
        return [
            'og:type' => 'product',
            'og:title' => $this->og_title ?? $this->getMetaTitleCompleto(),
            'og:description' => $this->og_description ?? $this->getMetaDescriptionCompleta(),
            'og:image' => $this->og_image ?? $producto?->imagen_principal,
            'og:url' => $this->canonical_url ?? url('/productos/' . $producto?->slug),
            'product:price:amount' => $producto?->precio_oferta ?? $producto?->precio,
            'product:price:currency' => $producto?->moneda ?? 'PEN',
        ];
    }
} 