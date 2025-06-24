<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CostoEnvioDinamico;
use App\Models\ZonaReparto;
use Illuminate\Database\Seeder;

class CostoEnvioDinamicoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zonas = ZonaReparto::all();

        foreach ($zonas as $zona) {
            $costos = $this->obtenerCostosPorZona($zona);
            
            foreach ($costos as $costo) {
                CostoEnvioDinamico::create(array_merge($costo, ['zona_reparto_id' => $zona->id]));
            }
        }
    }

    private function obtenerCostosPorZona(ZonaReparto $zona): array
    {
        switch ($zona->slug) {
            case 'zona-centro':
                return [
                    [
                        'distancia_desde_km' => 0.0,
                        'distancia_hasta_km' => 1.0,
                        'costo_envio' => 3.00,
                        'tiempo_adicional' => 0,
                        'activo' => true,
                    ],
                    [
                        'distancia_desde_km' => 1.0,
                        'distancia_hasta_km' => 2.5,
                        'costo_envio' => 5.00,
                        'tiempo_adicional' => 10,
                        'activo' => true,
                    ],
                    [
                        'distancia_desde_km' => 2.5,
                        'distancia_hasta_km' => 4.0,
                        'costo_envio' => 7.00,
                        'tiempo_adicional' => 20,
                        'activo' => true,
                    ],
                ];

            case 'zona-sur':
                return [
                    [
                        'distancia_desde_km' => 0.0,
                        'distancia_hasta_km' => 1.5,
                        'costo_envio' => 5.00,
                        'tiempo_adicional' => 0,
                        'activo' => true,
                    ],
                    [
                        'distancia_desde_km' => 1.5,
                        'distancia_hasta_km' => 3.0,
                        'costo_envio' => 7.00,
                        'tiempo_adicional' => 15,
                        'activo' => true,
                    ],
                    [
                        'distancia_desde_km' => 3.0,
                        'distancia_hasta_km' => 5.0,
                        'costo_envio' => 10.00,
                        'tiempo_adicional' => 30,
                        'activo' => true,
                    ],
                ];

            case 'zona-express':
                return [
                    [
                        'distancia_desde_km' => 0.0,
                        'distancia_hasta_km' => 1.0,
                        'costo_envio' => 8.00,
                        'tiempo_adicional' => -5, // 5 minutos menos por ser express
                        'activo' => true,
                    ],
                    [
                        'distancia_desde_km' => 1.0,
                        'distancia_hasta_km' => 2.0,
                        'costo_envio' => 10.00,
                        'tiempo_adicional' => -5,
                        'activo' => true,
                    ],
                    [
                        'distancia_desde_km' => 2.0,
                        'distancia_hasta_km' => 3.0,
                        'costo_envio' => 12.00,
                        'tiempo_adicional' => 0,
                        'activo' => true,
                    ],
                ];

            default:
                return [
                    [
                        'distancia_desde_km' => 0.0,
                        'distancia_hasta_km' => 5.0,
                        'costo_envio' => 6.00,
                        'tiempo_adicional' => 20,
                        'activo' => true,
                    ],
                ];
        }
    }
}
