<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Provincia;
use Illuminate\Database\Seeder;

class ProvinciaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener departamentos
        $lima = Departamento::where('codigo', 'LIM')->first();
        $callao = Departamento::where('codigo', 'CAL')->first();

        $provincias = [
            // Provincias de Lima
            ['departamento_id' => $lima->id, 'nombre' => 'Lima', 'codigo' => 'LIM01', 'codigo_inei' => '1501'],
            ['departamento_id' => $lima->id, 'nombre' => 'Barranca', 'codigo' => 'LIM02', 'codigo_inei' => '1502'],
            ['departamento_id' => $lima->id, 'nombre' => 'Cajatambo', 'codigo' => 'LIM03', 'codigo_inei' => '1503'],
            ['departamento_id' => $lima->id, 'nombre' => 'Canta', 'codigo' => 'LIM04', 'codigo_inei' => '1504'],
            ['departamento_id' => $lima->id, 'nombre' => 'Cañete', 'codigo' => 'LIM05', 'codigo_inei' => '1505'],
            ['departamento_id' => $lima->id, 'nombre' => 'Huaral', 'codigo' => 'LIM06', 'codigo_inei' => '1506'],
            ['departamento_id' => $lima->id, 'nombre' => 'Huarochirí', 'codigo' => 'LIM07', 'codigo_inei' => '1507'],
            ['departamento_id' => $lima->id, 'nombre' => 'Huaura', 'codigo' => 'LIM08', 'codigo_inei' => '1508'],
            ['departamento_id' => $lima->id, 'nombre' => 'Oyón', 'codigo' => 'LIM09', 'codigo_inei' => '1509'],
            ['departamento_id' => $lima->id, 'nombre' => 'Yauyos', 'codigo' => 'LIM10', 'codigo_inei' => '1510'],

            // Provincia del Callao
            ['departamento_id' => $callao->id, 'nombre' => 'Callao', 'codigo' => 'CAL01', 'codigo_inei' => '0701'],
        ];

        foreach ($provincias as $provincia) {
            Provincia::create($provincia);
        }
    }
}
