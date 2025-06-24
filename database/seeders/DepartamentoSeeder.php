<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Departamento;
use Illuminate\Database\Seeder;

class DepartamentoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departamentos = [
            ['nombre' => 'Lima', 'codigo' => 'LIM', 'codigo_inei' => '15', 'pais' => 'Perú'],
            ['nombre' => 'Arequipa', 'codigo' => 'ARE', 'codigo_inei' => '04', 'pais' => 'Perú'],
            ['nombre' => 'La Libertad', 'codigo' => 'LAL', 'codigo_inei' => '13', 'pais' => 'Perú'],
            ['nombre' => 'Piura', 'codigo' => 'PIU', 'codigo_inei' => '20', 'pais' => 'Perú'],
            ['nombre' => 'Lambayeque', 'codigo' => 'LAM', 'codigo_inei' => '14', 'pais' => 'Perú'],
            ['nombre' => 'Cusco', 'codigo' => 'CUS', 'codigo_inei' => '08', 'pais' => 'Perú'],
            ['nombre' => 'Junín', 'codigo' => 'JUN', 'codigo_inei' => '12', 'pais' => 'Perú'],
            ['nombre' => 'Callao', 'codigo' => 'CAL', 'codigo_inei' => '07', 'pais' => 'Perú'],
            ['nombre' => 'Ancash', 'codigo' => 'ANC', 'codigo_inei' => '02', 'pais' => 'Perú'],
            ['nombre' => 'Ica', 'codigo' => 'ICA', 'codigo_inei' => '11', 'pais' => 'Perú'],
        ];

        foreach ($departamentos as $departamento) {
            Departamento::create($departamento);
        }
    }
}
