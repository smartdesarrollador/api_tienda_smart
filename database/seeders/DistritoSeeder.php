<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Distrito;
use App\Models\Provincia;
use Illuminate\Database\Seeder;

class DistritoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener provincia de Lima
        $provinciaLima = Provincia::where('codigo', 'LIM01')->first();
        $provinciaCallao = Provincia::where('codigo', 'CAL01')->first();

        $distritos = [
            // Distritos de Lima donde hace delivery (según el usuario)
            [
                'provincia_id' => $provinciaLima->id,
                'nombre' => 'Lince',
                'codigo' => 'LIM0114',
                'codigo_inei' => '150114',
                'codigo_postal' => '15046',
                'latitud' => -12.0918,
                'longitud' => -77.0351,
                'disponible_delivery' => true,
            ],
            [
                'provincia_id' => $provinciaLima->id,
                'nombre' => 'San Borja',
                'codigo' => 'LIM0141',
                'codigo_inei' => '150141',
                'codigo_postal' => '15037',
                'latitud' => -12.1089,
                'longitud' => -76.9947,
                'disponible_delivery' => true,
            ],
            [
                'provincia_id' => $provinciaLima->id,
                'nombre' => 'Miraflores',
                'codigo' => 'LIM0118',
                'codigo_inei' => '150118',
                'codigo_postal' => '15074',
                'latitud' => -12.1211,
                'longitud' => -77.0281,
                'disponible_delivery' => true,
            ],
            [
                'provincia_id' => $provinciaLima->id,
                'nombre' => 'Jesús María',
                'codigo' => 'LIM0113',
                'codigo_inei' => '150113',
                'codigo_postal' => '15072',
                'latitud' => -12.0736,
                'longitud' => -77.0504,
                'disponible_delivery' => true,
            ],

            // Otros distritos de Lima (sin delivery por ahora)
            [
                'provincia_id' => $provinciaLima->id,
                'nombre' => 'Lima',
                'codigo' => 'LIM0101',
                'codigo_inei' => '150101',
                'codigo_postal' => '15001',
                'latitud' => -12.0464,
                'longitud' => -77.0428,
                'disponible_delivery' => false,
            ],
            [
                'provincia_id' => $provinciaLima->id,
                'nombre' => 'Barranco',
                'codigo' => 'LIM0104',
                'codigo_inei' => '150104',
                'codigo_postal' => '15063',
                'latitud' => -12.1404,
                'longitud' => -77.0196,
                'disponible_delivery' => false,
            ],
            [
                'provincia_id' => $provinciaLima->id,
                'nombre' => 'San Isidro',
                'codigo' => 'LIM0127',
                'codigo_inei' => '150127',
                'codigo_postal' => '15073',
                'latitud' => -12.1022,
                'longitud' => -77.0515,
                'disponible_delivery' => false,
            ],
            [
                'provincia_id' => $provinciaLima->id,
                'nombre' => 'Surco',
                'codigo' => 'LIM0150',
                'codigo_inei' => '150150',
                'codigo_postal' => '15038',
                'latitud' => -12.1687,
                'longitud' => -76.9906,
                'disponible_delivery' => false,
            ],

            // Distritos del Callao
            [
                'provincia_id' => $provinciaCallao->id,
                'nombre' => 'Callao',
                'codigo' => 'CAL0101',
                'codigo_inei' => '070101',
                'codigo_postal' => '07001',
                'latitud' => -12.0565,
                'longitud' => -77.1181,
                'disponible_delivery' => false,
            ],
        ];

        foreach ($distritos as $distrito) {
            Distrito::create($distrito);
        }
    }
}
