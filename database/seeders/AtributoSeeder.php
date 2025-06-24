<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Atributo;
use Illuminate\Database\Seeder;

class AtributoSeeder extends Seeder
{
    public function run(): void
    {
        Atributo::create([
            'nombre' => 'Color',
            'slug' => 'color',
            'tipo' => 'color',
            'filtrable' => true,
            'visible' => true,
        ]);

        Atributo::create([
            'nombre' => 'Tamaño',
            'slug' => 'tamaño',
            'tipo' => 'texto',
            'filtrable' => true,
            'visible' => true,
        ]);

        Atributo::create([
            'nombre' => 'Memoria RAM',
            'slug' => 'memoria-ram',
            'tipo' => 'numero',
            'filtrable' => true,
            'visible' => true,
        ]);

        Atributo::create([
            'nombre' => 'Almacenamiento',
            'slug' => 'almacenamiento',
            'tipo' => 'numero',
            'filtrable' => true,
            'visible' => true,
        ]);

        Atributo::create([
            'nombre' => 'Procesador',
            'slug' => 'procesador',
            'tipo' => 'texto',
            'filtrable' => true,
            'visible' => true,
        ]);

        Atributo::create([
            'nombre' => 'Pantalla',
            'slug' => 'pantalla',
            'tipo' => 'numero',
            'filtrable' => true,
            'visible' => true,
        ]);

        Atributo::create([
            'nombre' => 'Sistema Operativo',
            'slug' => 'sistema-operativo',
            'tipo' => 'texto',
            'filtrable' => true,
            'visible' => true,
        ]);

        Atributo::create([
            'nombre' => 'Conectividad',
            'slug' => 'conectividad',
            'tipo' => 'texto',
            'filtrable' => true,
            'visible' => true,
        ]);

        Atributo::create([
            'nombre' => 'Batería',
            'slug' => 'bateria',
            'tipo' => 'numero',
            'filtrable' => false,
            'visible' => true,
        ]);

        Atributo::create([
            'nombre' => 'Material',
            'slug' => 'material',
            'tipo' => 'texto',
            'filtrable' => true,
            'visible' => true,
        ]);
    }
} 