<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ExcepcionZona;
use App\Models\ZonaReparto;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ExcepcionZonaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zonas = ZonaReparto::all();

        foreach ($zonas as $zona) {
            $excepciones = $this->obtenerExcepcionesPorZona($zona);
            
            foreach ($excepciones as $excepcion) {
                ExcepcionZona::create(array_merge($excepcion, ['zona_reparto_id' => $zona->id]));
            }
        }
    }

    private function obtenerExcepcionesPorZona(ZonaReparto $zona): array
    {
        $baseExcepciones = [
            // Año Nuevo
            [
                'fecha_excepcion' => '2024-01-01',
                'tipo' => 'horario_especial',
                'hora_inicio' => '14:00:00',
                'hora_fin' => '20:00:00',
                'costo_especial' => $zona->costo_envio * 1.5,
                'tiempo_especial_min' => 45,
                'tiempo_especial_max' => 90,
                'motivo' => 'Año Nuevo - Servicio limitado de 2pm a 8pm',
                'activo' => true,
            ],
            
            // Día del Trabajo
            [
                'fecha_excepcion' => '2024-05-01',
                'tipo' => 'no_disponible',
                'hora_inicio' => null,
                'hora_fin' => null,
                'costo_especial' => null,
                'tiempo_especial_min' => null,
                'tiempo_especial_max' => null,
                'motivo' => 'Día del Trabajo - Sin servicio de delivery',
                'activo' => true,
            ],

            // Fiestas Patrias - Día 1
            [
                'fecha_excepcion' => '2024-07-28',
                'tipo' => 'costo_especial',
                'hora_inicio' => '12:00:00',
                'hora_fin' => '22:00:00',
                'costo_especial' => $zona->costo_envio * 2.0,
                'tiempo_especial_min' => 60,
                'tiempo_especial_max' => 120,
                'motivo' => 'Fiestas Patrias - Independencia del Perú',
                'activo' => true,
            ],

            // Fiestas Patrias - Día 2
            [
                'fecha_excepcion' => '2024-07-29',
                'tipo' => 'costo_especial',
                'hora_inicio' => '12:00:00',
                'hora_fin' => '22:00:00',
                'costo_especial' => $zona->costo_envio * 2.0,
                'tiempo_especial_min' => 60,
                'tiempo_especial_max' => 120,
                'motivo' => 'Fiestas Patrias - Día de la Independencia',
                'activo' => true,
            ],

            // Navidad
            [
                'fecha_excepcion' => '2024-12-25',
                'tipo' => 'tiempo_especial',
                'hora_inicio' => '16:00:00',
                'hora_fin' => '21:00:00',
                'costo_especial' => $zona->costo_envio * 1.8,
                'tiempo_especial_min' => 90,
                'tiempo_especial_max' => 150,
                'motivo' => 'Navidad - Horario especial navideño',
                'activo' => true,
            ],

            // Mantenimiento programado
            [
                'fecha_excepcion' => Carbon::now()->addMonth()->format('Y-m-d'),
                'tipo' => 'no_disponible',
                'hora_inicio' => null,
                'hora_fin' => null,
                'costo_especial' => null,
                'tiempo_especial_min' => null,
                'tiempo_especial_max' => null,
                'motivo' => 'Mantenimiento programado del sistema de delivery',
                'activo' => true,
            ],
        ];

        // Personalizar excepciones según la zona
        switch ($zona->slug) {
            case 'zona-express':
                // Zona express no opera en feriados, solo mantenimiento
                return array_filter($baseExcepciones, function ($excepcion) {
                    return $excepcion['tipo'] === 'no_disponible';
                });

            case 'zona-sur':
                // Zona sur tiene mejor servicio en eventos especiales
                return array_map(function ($excepcion) {
                    if ($excepcion['tipo'] === 'tiempo_especial') {
                        $excepcion['tiempo_especial_min'] = max(30, $excepcion['tiempo_especial_min'] - 15);
                        $excepcion['tiempo_especial_max'] = max(45, $excepcion['tiempo_especial_max'] - 15);
                        $excepcion['motivo'] .= ' (Zona premium con tiempos optimizados)';
                    }
                    return $excepcion;
                }, $baseExcepciones);

            default:
                return $baseExcepciones;
        }
    }
}
