<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        // Categorías padre
        $smartphones = Categoria::create([
            'nombre' => 'Smartphones',
            'slug' => 'smartphones',
            'descripcion' => 'Teléfonos inteligentes de última generación',
            'imagen' => 'assets/categorias/samsung.png',
            'activo' => true,
            'orden' => 1,
            'meta_title' => 'Smartphones - Teléfonos Inteligentes',
            'meta_description' => 'Los mejores smartphones del mercado con precios increíbles',
        ]);

        $laptops = Categoria::create([
            'nombre' => 'Laptops',
            'slug' => 'laptops',
            'descripcion' => 'Computadoras portátiles para trabajo y entretenimiento',
            'imagen' => 'assets/categorias/laptop.png',
            'activo' => true,
            'orden' => 2,
            'meta_title' => 'Laptops - Computadoras Portátiles',
            'meta_description' => 'Laptops de alta calidad para profesionales y estudiantes',
        ]);

        $electrodomesticos = Categoria::create([
            'nombre' => 'Electrodomésticos',
            'slug' => 'electrodomesticos',
            'descripcion' => 'Electrodomésticos para el hogar',
            'imagen' => 'assets/categorias/refrigeradora.png',
            'activo' => true,
            'orden' => 3,
            'meta_title' => 'Electrodomésticos para el Hogar',
            'meta_description' => 'Los mejores electrodomésticos para tu hogar',
        ]);

        // Subcategorías de Smartphones
        Categoria::create([
            'nombre' => 'iPhone',
            'slug' => 'iphone',
            'descripcion' => 'Smartphones Apple iPhone',
            'imagen' => 'assets/categorias/iphone.png',
            'activo' => true,
            'orden' => 1,
            'categoria_padre_id' => $smartphones->id,
            'meta_title' => 'iPhone - Smartphones Apple',
            'meta_description' => 'Los últimos modelos de iPhone con garantía oficial',
        ]);

        Categoria::create([
            'nombre' => 'Samsung',
            'slug' => 'samsung',
            'descripcion' => 'Smartphones Samsung Galaxy',
            'imagen' => 'assets/categorias/samsung.png',
            'activo' => true,
            'orden' => 2,
            'categoria_padre_id' => $smartphones->id,
            'meta_title' => 'Samsung Galaxy - Smartphones',
            'meta_description' => 'Smartphones Samsung Galaxy de última generación',
        ]);

        // Subcategorías de Electrodomésticos
        Categoria::create([
            'nombre' => 'Refrigeradoras',
            'slug' => 'refrigeradoras',
            'descripcion' => 'Refrigeradoras y congeladoras',
            'imagen' => 'assets/categorias/refrigeradora.png',
            'activo' => true,
            'orden' => 1,
            'categoria_padre_id' => $electrodomesticos->id,
            'meta_title' => 'Refrigeradoras',
            'meta_description' => 'Refrigeradoras eficientes para tu hogar',
        ]);

        Categoria::create([
            'nombre' => 'Lavadoras',
            'slug' => 'lavadoras',
            'descripcion' => 'Lavadoras y secadoras',
            'imagen' => 'assets/categorias/lavadora.png',
            'activo' => true,
            'orden' => 2,
            'categoria_padre_id' => $electrodomesticos->id,
            'meta_title' => 'Lavadoras',
            'meta_description' => 'Lavadoras eficientes para tu hogar',
        ]);
    }
} 