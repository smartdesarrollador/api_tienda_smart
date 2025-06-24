<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ImagenProducto;
use App\Models\Producto;
use Illuminate\Database\Seeder;

class ImagenProductoSeeder extends Seeder
{
    public function run(): void
    {
        $productos = Producto::all();

        foreach ($productos as $producto) {
            // Imagen principal ya está en el producto
            // Crear 2-4 imágenes adicionales por producto
            $cantidadImagenes = rand(2, 4);
            
            // Obtener el nombre base de la imagen principal para mantener consistencia
            $imagenPrincipal = $producto->imagen_principal;
            $nombreBase = pathinfo($imagenPrincipal, PATHINFO_FILENAME);
            $extension = pathinfo($imagenPrincipal, PATHINFO_EXTENSION);
            
            for ($i = 1; $i <= $cantidadImagenes; $i++) {
                ImagenProducto::create([
                    'url' => "assets/productos/{$nombreBase}-{$i}.{$extension}",
                    'alt' => "{$producto->nombre} - Vista {$i}",
                    'orden' => $i,
                    'principal' => false,
                    'producto_id' => $producto->id,
                    'tipo' => match ($i) {
                        1 => 'galeria',
                        2 => 'detalle',
                        3 => 'zoom',
                        default => 'galeria',
                    },
                ]);
            }
            
            // Agregar imagen de embalaje
            ImagenProducto::create([
                'url' => "assets/productos/{$nombreBase}-embalaje.{$extension}",
                'alt' => "{$producto->nombre} - Embalaje",
                'orden' => $cantidadImagenes + 1,
                'principal' => false,
                'producto_id' => $producto->id,
                'tipo' => 'embalaje',
            ]);
        }
    }
} 