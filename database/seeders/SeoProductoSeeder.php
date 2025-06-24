<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SeoProducto;
use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SeoProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productos = Producto::all();

        foreach ($productos as $producto) {
            $seo = [
                'producto_id' => $producto->id,
                'meta_title' => $this->generarMetaTitle($producto),
                'meta_description' => $this->generarMetaDescription($producto),
                'meta_keywords' => $this->generarMetaKeywords($producto),
                'canonical_url' => null, // Se puede generar dinámicamente
                'schema_markup' => json_encode($this->generarSchemaMarkup($producto)),
                'og_title' => $this->generarOgTitle($producto),
                'og_description' => $this->generarOgDescription($producto),
                'og_image' => $producto->imagen ?? '/assets/productos/default.jpg',
            ];

            SeoProducto::create($seo);
        }
    }

    private function generarMetaTitle(Producto $producto): string
    {
        $baseTitle = $producto->nombre;
        $complementos = [
            'Delivery Lima | Tienda Virtual',
            'Comida Rápida | Delivery',
            'Hamburguesas Gourmet Lima',
            'Mejor Sabor | Delivery Express',
            'Lima Delivery | Comida Casera'
        ];
        
        $complemento = $complementos[array_rand($complementos)];
        $title = "{$baseTitle} - {$complemento}";
        
        // Limitar a 60 caracteres para SEO
        return Str::limit($title, 60, '');
    }

    private function generarMetaDescription(Producto $producto): string
    {
        $descripciones = [
            "Deliciosa {$producto->nombre} preparada con ingredientes frescos. Delivery en Lima: Lince, San Borja, Miraflores, Jesús María. ¡Ordena ahora!",
            "Disfruta de {$producto->nombre} con el mejor sabor de Lima. Entrega rápida en 30-45 minutos. Ingredientes premium y atención personalizada.",
            "{$producto->nombre} casera y gourmet. Delivery express en zonas seleccionadas de Lima. Calidad garantizada y precios justos.",
            "La mejor {$producto->nombre} de Lima te espera. Preparación artesanal, ingredientes frescos. Delivery seguro y rápido a tu hogar.",
        ];
        
        $descripcion = $descripciones[array_rand($descripciones)];
        
        // Limitar a 160 caracteres para SEO
        return Str::limit($descripcion, 160, '');
    }

    private function generarMetaKeywords(Producto $producto): string
    {
        $keywords = [
            strtolower($producto->nombre),
            'delivery lima',
            'comida rápida',
            'hamburguesas',
            'delivery lince',
            'delivery san borja',
            'delivery miraflores',
            'delivery jesús maría',
            'comida casera',
            'delivery express',
            'hamburguesas gourmet',
            'comida peruana',
            'fast food',
            'delivery online'
        ];
        
        return implode(', ', array_slice($keywords, 0, 10));
    }

    private function generarSlugPersonalizado(Producto $producto): string
    {
        $slug = Str::slug($producto->nombre);
        $sufijos = ['lima', 'delivery', 'gourmet', 'express', 'premium'];
        $sufijo = $sufijos[array_rand($sufijos)];
        
        return "{$slug}-{$sufijo}";
    }

    private function generarOgTitle(Producto $producto): string
    {
        return "{$producto->nombre} - Delivery Lima | Comida Gourmet";
    }

    private function generarOgDescription(Producto $producto): string
    {
        return "Prueba nuestra deliciosa {$producto->nombre}. Delivery rápido en Lima con ingredientes frescos y sabor casero.";
    }

    private function generarTwitterTitle(Producto $producto): string
    {
        return "{$producto->nombre} 🍔 Delivery Lima";
    }

    private function generarTwitterDescription(Producto $producto): string
    {
        return "¡Antojo de {$producto->nombre}? 🤤 Delivery express en Lima. Ingredientes frescos y sabor único. #DeliveryLima #ComidaGourmet";
    }

    private function generarSchemaMarkup(Producto $producto): array
    {
        return [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $producto->nombre,
            'description' => $producto->descripcion ?? "Deliciosa {$producto->nombre}",
            'image' => $producto->imagen ?? '/assets/productos/default.jpg',
            'offers' => [
                '@type' => 'Offer',
                'price' => $producto->precio,
                'priceCurrency' => 'PEN',
                'availability' => 'https://schema.org/InStock',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => 'Tienda Virtual Delivery'
                ]
            ],
            'brand' => [
                '@type' => 'Brand',
                'name' => 'Tienda Virtual'
            ],
            'category' => $producto->categoria->nombre ?? 'Comida'
        ];
    }

    private function generarBreadcrumbs(Producto $producto): array
    {
        return [
            [
                'name' => 'Inicio',
                'url' => '/'
            ],
            [
                'name' => 'Productos',
                'url' => '/productos'
            ],
            [
                'name' => ucfirst($producto->categoria->nombre ?? 'Comida'),
                'url' => '/productos/' . Str::slug($producto->categoria->nombre ?? 'comida')
            ],
            [
                'name' => $producto->nombre,
                'url' => '/productos/' . Str::slug($producto->nombre)
            ]
        ];
    }

    private function generarFocusKeyword(Producto $producto): string
    {
        $keywords = [
            $producto->nombre . ' delivery',
            $producto->nombre . ' lima',
            'delivery ' . strtolower($producto->nombre),
            strtolower($producto->nombre) . ' gourmet'
        ];
        
        return $keywords[array_rand($keywords)];
    }

    private function generarAltText(Producto $producto): array
    {
        return [
            'imagen_principal' => "{$producto->nombre} deliciosa preparada con ingredientes frescos",
            'imagen_detalle' => "Detalle de {$producto->nombre} gourmet para delivery",
            'imagen_ingredientes' => "Ingredientes frescos para {$producto->nombre}",
        ];
    }
}
