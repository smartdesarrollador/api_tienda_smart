<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Adicional;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdicionalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adicionales = [
            // Salsas
            [
                'nombre' => 'Salsa BBQ',
                'slug' => 'salsa-bbq',
                'descripcion' => 'Salsa barbacoa casera con sabor ahumado',
                'precio' => 2.50,
                'tipo' => 'salsa',
                'disponible' => true,
                'activo' => true,
                'stock' => null, // Sin límite
                'tiempo_preparacion' => 0,
                'calorias' => 45.0,
                'informacion_nutricional' => [
                    'carbohidratos' => '11g',
                    'azucares' => '9g',
                    'sodio' => '320mg'
                ],
                'alergenos' => [],
                'vegetariano' => true,
                'vegano' => false,
                'orden' => 1,
            ],
            [
                'nombre' => 'Salsa Ketchup',
                'slug' => 'salsa-ketchup',
                'descripcion' => 'Ketchup tradicional de tomate',
                'precio' => 1.50,
                'tipo' => 'salsa',
                'disponible' => true,
                'activo' => true,
                'stock' => null,
                'tiempo_preparacion' => 0,
                'calorias' => 20.0,
                'alergenos' => [],
                'vegetariano' => true,
                'vegano' => true,
                'orden' => 2,
            ],
            [
                'nombre' => 'Mayonesa Casera',
                'slug' => 'mayonesa-casera',
                'descripcion' => 'Mayonesa artesanal cremosa',
                'precio' => 2.00,
                'tipo' => 'salsa',
                'disponible' => true,
                'activo' => true,
                'stock' => null,
                'tiempo_preparacion' => 0,
                'calorias' => 94.0,
                'alergenos' => ['huevo'],
                'vegetariano' => true,
                'vegano' => false,
                'orden' => 3,
            ],
            [
                'nombre' => 'Salsa Ají Amarillo',
                'slug' => 'salsa-aji-amarillo',
                'descripcion' => 'Salsa picante peruana de ají amarillo',
                'precio' => 3.00,
                'tipo' => 'salsa',
                'disponible' => true,
                'activo' => true,
                'stock' => null,
                'tiempo_preparacion' => 0,
                'calorias' => 15.0,
                'alergenos' => [],
                'vegetariano' => true,
                'vegano' => true,
                'orden' => 4,
            ],

            // Quesos
            [
                'nombre' => 'Queso Cheddar',
                'slug' => 'queso-cheddar',
                'descripcion' => 'Queso cheddar amarillo derretido',
                'precio' => 4.00,
                'tipo' => 'queso',
                'disponible' => true,
                'activo' => true,
                'stock' => 50,
                'tiempo_preparacion' => 2,
                'calorias' => 113.0,
                'informacion_nutricional' => [
                    'proteinas' => '7g',
                    'grasas' => '9g',
                    'calcio' => '200mg'
                ],
                'alergenos' => ['lactosa'],
                'vegetariano' => true,
                'vegano' => false,
                'orden' => 1,
            ],
            [
                'nombre' => 'Queso Mozzarella',
                'slug' => 'queso-mozzarella',
                'descripcion' => 'Queso mozzarella fresco derretido',
                'precio' => 4.50,
                'tipo' => 'queso',
                'disponible' => true,
                'activo' => true,
                'stock' => 45,
                'tiempo_preparacion' => 2,
                'calorias' => 85.0,
                'alergenos' => ['lactosa'],
                'vegetariano' => true,
                'vegano' => false,
                'orden' => 2,
            ],
            [
                'nombre' => 'Queso Suizo',
                'slug' => 'queso-suizo',
                'descripcion' => 'Queso suizo con sabor suave',
                'precio' => 5.00,
                'tipo' => 'queso',
                'disponible' => true,
                'activo' => true,
                'stock' => 30,
                'tiempo_preparacion' => 2,
                'calorias' => 106.0,
                'alergenos' => ['lactosa'],
                'vegetariano' => true,
                'vegano' => false,
                'orden' => 3,
            ],

            // Carnes
            [
                'nombre' => 'Tocino Ahumado',
                'slug' => 'tocino-ahumado',
                'descripcion' => 'Tocino crujiente ahumado en casa',
                'precio' => 6.00,
                'tipo' => 'carne',
                'disponible' => true,
                'activo' => true,
                'stock' => 25,
                'tiempo_preparacion' => 5,
                'calorias' => 161.0,
                'informacion_nutricional' => [
                    'proteinas' => '12g',
                    'grasas' => '12g',
                    'sodio' => '435mg'
                ],
                'alergenos' => [],
                'vegetariano' => false,
                'vegano' => false,
                'orden' => 1,
            ],
            [
                'nombre' => 'Pollo Crispy',
                'slug' => 'pollo-crispy',
                'descripcion' => 'Tiras de pollo empanizado crujiente',
                'precio' => 7.00,
                'tipo' => 'carne',
                'disponible' => true,
                'activo' => true,
                'stock' => 20,
                'tiempo_preparacion' => 8,
                'calorias' => 231.0,
                'alergenos' => ['gluten'],
                'vegetariano' => false,
                'vegano' => false,
                'orden' => 2,
            ],

            // Vegetales
            [
                'nombre' => 'Cebolla Caramelizada',
                'slug' => 'cebolla-caramelizada',
                'descripcion' => 'Cebolla dulce caramelizada lentamente',
                'precio' => 3.50,
                'tipo' => 'vegetal',
                'disponible' => true,
                'activo' => true,
                'stock' => null,
                'tiempo_preparacion' => 3,
                'calorias' => 46.0,
                'alergenos' => [],
                'vegetariano' => true,
                'vegano' => true,
                'orden' => 1,
            ],
            [
                'nombre' => 'Palta Extra',
                'slug' => 'palta-extra',
                'descripcion' => 'Rodajas adicionales de palta fresca',
                'precio' => 4.50,
                'tipo' => 'vegetal',
                'disponible' => true,
                'activo' => true,
                'stock' => 15,
                'tiempo_preparacion' => 1,
                'calorias' => 160.0,
                'alergenos' => [],
                'vegetariano' => true,
                'vegano' => true,
                'orden' => 2,
            ],
            [
                'nombre' => 'Champiñones Salteados',
                'slug' => 'champinones-salteados',
                'descripcion' => 'Champiñones frescos salteados con ajo',
                'precio' => 4.00,
                'tipo' => 'vegetal',
                'disponible' => true,
                'activo' => true,
                'stock' => null,
                'tiempo_preparacion' => 4,
                'calorias' => 22.0,
                'alergenos' => [],
                'vegetariano' => true,
                'vegano' => true,
                'orden' => 3,
            ],
        ];

        foreach ($adicionales as $adicional) {
            Adicional::create($adicional);
        }
    }
}
