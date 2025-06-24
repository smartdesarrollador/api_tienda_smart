<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Ejecutar el seeder.
     */
    public function run(): void
    {
        $banners = [
            [
                'titulo' => 'Bienvenido a Credimaquimotora',
                'descripcion' => 'Explora nuestro catálogo y encuentra los mejores productos al mejor precio.',
                'imagen' => 'assets/banners/banner1.jpg',
                'texto_boton' => 'Conoce más',
                'enlace_boton' => '/catalogo',
                'orden' => 1,
                'activo' => true,
            ],
            [
                'titulo' => 'Catálogo de Productos',
                'descripcion' => 'Descubre nuestra amplia selección de productos de calidad para todas tus necesidades.',
                'imagen' => 'assets/banners/banner2.jpg',
                'texto_boton' => 'Ver catálogo',
                'enlace_boton' => '/catalogo',
                'orden' => 2,
                'activo' => true,
            ],
            [
                'titulo' => 'Ofertas especiales',
                'descripcion' => 'Aprovecha nuestras ofertas por tiempo limitado con descuentos increíbles.',
                'imagen' => 'assets/banners/banner3.jpg',
                'texto_boton' => 'Ver ofertas',
                'enlace_boton' => '/ofertas',
                'orden' => 3,
                'activo' => false,
            ],
        ];

        foreach ($banners as $banner) {
            Banner::create($banner);
        }
    }
} 