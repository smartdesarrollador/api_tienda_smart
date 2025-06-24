<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Atributo;
use App\Models\ValorAtributo;
use Illuminate\Database\Seeder;

class ValorAtributoSeeder extends Seeder
{
    public function run(): void
    {
        // Valores para Color (ID: 1)
        $colorAtributo = Atributo::where('slug', 'color')->first();
        if ($colorAtributo) {
            ValorAtributo::create([
                'atributo_id' => $colorAtributo->id,
                'valor' => 'Negro',
                'codigo' => '#000000',
            ]);

            ValorAtributo::create([
                'atributo_id' => $colorAtributo->id,
                'valor' => 'Blanco',
                'codigo' => '#FFFFFF',
            ]);

            ValorAtributo::create([
                'atributo_id' => $colorAtributo->id,
                'valor' => 'Azul',
                'codigo' => '#0066CC',
            ]);

            ValorAtributo::create([
                'atributo_id' => $colorAtributo->id,
                'valor' => 'Rojo',
                'codigo' => '#CC0000',
            ]);

            ValorAtributo::create([
                'atributo_id' => $colorAtributo->id,
                'valor' => 'Dorado',
                'codigo' => '#FFD700',
            ]);

            ValorAtributo::create([
                'atributo_id' => $colorAtributo->id,
                'valor' => 'Plata',
                'codigo' => '#C0C0C0',
            ]);
        }

        // Valores para Tamaño (ID: 2)
        $tamañoAtributo = Atributo::where('slug', 'tamaño')->first();
        if ($tamañoAtributo) {
            ValorAtributo::create([
                'atributo_id' => $tamañoAtributo->id,
                'valor' => '13"',
            ]);

            ValorAtributo::create([
                'atributo_id' => $tamañoAtributo->id,
                'valor' => '14"',
            ]);

            ValorAtributo::create([
                'atributo_id' => $tamañoAtributo->id,
                'valor' => '15.6"',
            ]);

            ValorAtributo::create([
                'atributo_id' => $tamañoAtributo->id,
                'valor' => '17"',
            ]);
        }

        // Valores para Memoria RAM (ID: 3)
        $ramAtributo = Atributo::where('slug', 'memoria-ram')->first();
        if ($ramAtributo) {
            ValorAtributo::create([
                'atributo_id' => $ramAtributo->id,
                'valor' => '4GB',
            ]);

            ValorAtributo::create([
                'atributo_id' => $ramAtributo->id,
                'valor' => '8GB',
            ]);

            ValorAtributo::create([
                'atributo_id' => $ramAtributo->id,
                'valor' => '16GB',
            ]);

            ValorAtributo::create([
                'atributo_id' => $ramAtributo->id,
                'valor' => '32GB',
            ]);
        }

        // Valores para Almacenamiento (ID: 4)
        $almacenamientoAtributo = Atributo::where('slug', 'almacenamiento')->first();
        if ($almacenamientoAtributo) {
            ValorAtributo::create([
                'atributo_id' => $almacenamientoAtributo->id,
                'valor' => '128GB',
            ]);

            ValorAtributo::create([
                'atributo_id' => $almacenamientoAtributo->id,
                'valor' => '256GB',
            ]);

            ValorAtributo::create([
                'atributo_id' => $almacenamientoAtributo->id,
                'valor' => '512GB',
            ]);

            ValorAtributo::create([
                'atributo_id' => $almacenamientoAtributo->id,
                'valor' => '1TB',
            ]);
        }

        // Valores para Procesador (ID: 5)
        $procesadorAtributo = Atributo::where('slug', 'procesador')->first();
        if ($procesadorAtributo) {
            ValorAtributo::create([
                'atributo_id' => $procesadorAtributo->id,
                'valor' => 'Intel Core i3',
            ]);

            ValorAtributo::create([
                'atributo_id' => $procesadorAtributo->id,
                'valor' => 'Intel Core i5',
            ]);

            ValorAtributo::create([
                'atributo_id' => $procesadorAtributo->id,
                'valor' => 'Intel Core i7',
            ]);

            ValorAtributo::create([
                'atributo_id' => $procesadorAtributo->id,
                'valor' => 'AMD Ryzen 5',
            ]);

            ValorAtributo::create([
                'atributo_id' => $procesadorAtributo->id,
                'valor' => 'AMD Ryzen 7',
            ]);

            ValorAtributo::create([
                'atributo_id' => $procesadorAtributo->id,
                'valor' => 'Apple M1',
            ]);

            ValorAtributo::create([
                'atributo_id' => $procesadorAtributo->id,
                'valor' => 'Apple M2',
            ]);
        }

        // Valores para Pantalla (ID: 6)
        $pantallaAtributo = Atributo::where('slug', 'pantalla')->first();
        if ($pantallaAtributo) {
            ValorAtributo::create([
                'atributo_id' => $pantallaAtributo->id,
                'valor' => '5.5"',
            ]);

            ValorAtributo::create([
                'atributo_id' => $pantallaAtributo->id,
                'valor' => '6.1"',
            ]);

            ValorAtributo::create([
                'atributo_id' => $pantallaAtributo->id,
                'valor' => '6.7"',
            ]);

            ValorAtributo::create([
                'atributo_id' => $pantallaAtributo->id,
                'valor' => '10.1"',
            ]);

            ValorAtributo::create([
                'atributo_id' => $pantallaAtributo->id,
                'valor' => '12.9"',
            ]);
        }

        // Valores para Sistema Operativo (ID: 7)
        $soAtributo = Atributo::where('slug', 'sistema-operativo')->first();
        if ($soAtributo) {
            ValorAtributo::create([
                'atributo_id' => $soAtributo->id,
                'valor' => 'iOS',
            ]);

            ValorAtributo::create([
                'atributo_id' => $soAtributo->id,
                'valor' => 'Android',
            ]);

            ValorAtributo::create([
                'atributo_id' => $soAtributo->id,
                'valor' => 'Windows 11',
            ]);

            ValorAtributo::create([
                'atributo_id' => $soAtributo->id,
                'valor' => 'macOS',
            ]);
        }

        // Valores para Conectividad (ID: 8)
        $conectividadAtributo = Atributo::where('slug', 'conectividad')->first();
        if ($conectividadAtributo) {
            ValorAtributo::create([
                'atributo_id' => $conectividadAtributo->id,
                'valor' => '4G',
            ]);

            ValorAtributo::create([
                'atributo_id' => $conectividadAtributo->id,
                'valor' => '5G',
            ]);

            ValorAtributo::create([
                'atributo_id' => $conectividadAtributo->id,
                'valor' => 'WiFi',
            ]);

            ValorAtributo::create([
                'atributo_id' => $conectividadAtributo->id,
                'valor' => 'Bluetooth',
            ]);
        }

        // Valores para Material (ID: 10)
        $materialAtributo = Atributo::where('slug', 'material')->first();
        if ($materialAtributo) {
            ValorAtributo::create([
                'atributo_id' => $materialAtributo->id,
                'valor' => 'Aluminio',
            ]);

            ValorAtributo::create([
                'atributo_id' => $materialAtributo->id,
                'valor' => 'Plástico',
            ]);

            ValorAtributo::create([
                'atributo_id' => $materialAtributo->id,
                'valor' => 'Vidrio',
            ]);

            ValorAtributo::create([
                'atributo_id' => $materialAtributo->id,
                'valor' => 'Fibra de Carbono',
            ]);
        }
    }
} 