<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GrupoAdicional;
use App\Models\Adicional;
use Illuminate\Database\Seeder;

class GrupoAdicionalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $grupos = [
            [
                'nombre' => 'Salsas',
                'slug' => 'salsas',
                'descripcion' => 'Selecciona tus salsas favoritas para acompañar tu hamburguesa',
                'obligatorio' => false,
                'multiple_seleccion' => true,
                'minimo_selecciones' => 0,
                'maximo_selecciones' => 3,
                'orden' => 1,
                'activo' => true,
            ],
            [
                'nombre' => 'Quesos',
                'slug' => 'quesos',
                'descripcion' => 'Elige el queso que más te guste',
                'obligatorio' => false,
                'multiple_seleccion' => false,
                'minimo_selecciones' => 0,
                'maximo_selecciones' => 1,
                'orden' => 2,
                'activo' => true,
            ],
            [
                'nombre' => 'Proteínas Extra',
                'slug' => 'proteinas-extra',
                'descripcion' => 'Agrega proteína adicional a tu hamburguesa',
                'obligatorio' => false,
                'multiple_seleccion' => true,
                'minimo_selecciones' => 0,
                'maximo_selecciones' => 2,
                'orden' => 3,
                'activo' => true,
            ],
            [
                'nombre' => 'Vegetales Premium',
                'slug' => 'vegetales-premium',
                'descripcion' => 'Vegetales gourmet para complementar tu experiencia',
                'obligatorio' => false,
                'multiple_seleccion' => true,
                'minimo_selecciones' => 0,
                'maximo_selecciones' => null, // Sin límite
                'orden' => 4,
                'activo' => true,
            ],
            [
                'nombre' => 'Combos Salsas',
                'slug' => 'combos-salsas',
                'descripcion' => 'Elige al menos una salsa para tu hamburguesa',
                'obligatorio' => true,
                'multiple_seleccion' => true,
                'minimo_selecciones' => 1,
                'maximo_selecciones' => 2,
                'orden' => 5,
                'activo' => true,
            ],
        ];

        foreach ($grupos as $grupo) {
            $grupoCreado = GrupoAdicional::create($grupo);

            // Asignar adicionales a grupos según su tipo
            switch ($grupo['slug']) {
                case 'salsas':
                case 'combos-salsas':
                    $adicionales = Adicional::where('tipo', 'salsa')->get();
                    foreach ($adicionales as $index => $adicional) {
                        $grupoCreado->adicionales()->attach($adicional->id, ['orden' => $index + 1]);
                    }
                    break;

                case 'quesos':
                    $adicionales = Adicional::where('tipo', 'queso')->get();
                    foreach ($adicionales as $index => $adicional) {
                        $grupoCreado->adicionales()->attach($adicional->id, ['orden' => $index + 1]);
                    }
                    break;

                case 'proteinas-extra':
                    $adicionales = Adicional::where('tipo', 'carne')->get();
                    foreach ($adicionales as $index => $adicional) {
                        $grupoCreado->adicionales()->attach($adicional->id, ['orden' => $index + 1]);
                    }
                    break;

                case 'vegetales-premium':
                    $adicionales = Adicional::where('tipo', 'vegetal')->get();
                    foreach ($adicionales as $index => $adicional) {
                        $grupoCreado->adicionales()->attach($adicional->id, ['orden' => $index + 1]);
                    }
                    break;
            }
        }
    }
}
