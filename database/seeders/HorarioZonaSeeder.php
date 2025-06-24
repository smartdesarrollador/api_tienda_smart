<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HorarioZona;
use App\Models\ZonaReparto;
use Illuminate\Database\Seeder;

class HorarioZonaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zonas = ZonaReparto::all();

        foreach ($zonas as $zona) {
            $horarios = $this->obtenerHorariosPorZona($zona);
            
            foreach ($horarios as $horario) {
                HorarioZona::create(array_merge($horario, ['zona_reparto_id' => $zona->id]));
            }
        }
    }

    private function obtenerHorariosPorZona(ZonaReparto $zona): array
    {
        // Horarios base para todas las zonas
        $horariosBase = [
            ['dia_semana' => 'lunes', 'hora_inicio' => '11:00', 'hora_fin' => '22:00'],
            ['dia_semana' => 'martes', 'hora_inicio' => '11:00', 'hora_fin' => '22:00'],
            ['dia_semana' => 'miercoles', 'hora_inicio' => '11:00', 'hora_fin' => '22:00'],
            ['dia_semana' => 'jueves', 'hora_inicio' => '11:00', 'hora_fin' => '22:00'],
            ['dia_semana' => 'viernes', 'hora_inicio' => '11:00', 'hora_fin' => '23:00'],
            ['dia_semana' => 'sabado', 'hora_inicio' => '10:00', 'hora_fin' => '23:30'],
            ['dia_semana' => 'domingo', 'hora_inicio' => '11:00', 'hora_fin' => '21:00'],
        ];

        // Personalizar horarios según la zona
        switch ($zona->slug) {
            case 'zona-centro':
                // Zona centro tiene horarios normales
                return array_map(function ($horario) {
                    return array_merge($horario, [
                        'activo' => true,
                        'dia_completo' => false,
                        'observaciones' => 'Horario normal para zona centro'
                    ]);
                }, $horariosBase);

            case 'zona-sur':
                // Zona sur extiende horarios los fines de semana
                return array_map(function ($horario) {
                    $observaciones = 'Zona residencial';
                    
                    if (in_array($horario['dia_semana'], ['viernes', 'sabado'])) {
                        $horario['hora_fin'] = '00:00';
                        $observaciones = 'Horario extendido fin de semana';
                    }
                    
                    return array_merge($horario, [
                        'activo' => true,
                        'dia_completo' => false,
                        'observaciones' => $observaciones
                    ]);
                }, $horariosBase);

            case 'zona-express':
                // Zona express solo opera en horarios de oficina extendidos
                $horariosExpress = [
                    ['dia_semana' => 'lunes', 'hora_inicio' => '08:00', 'hora_fin' => '18:00'],
                    ['dia_semana' => 'martes', 'hora_inicio' => '08:00', 'hora_fin' => '18:00'],
                    ['dia_semana' => 'miercoles', 'hora_inicio' => '08:00', 'hora_fin' => '18:00'],
                    ['dia_semana' => 'jueves', 'hora_inicio' => '08:00', 'hora_fin' => '18:00'],
                    ['dia_semana' => 'viernes', 'hora_inicio' => '08:00', 'hora_fin' => '19:00'],
                    ['dia_semana' => 'sabado', 'hora_inicio' => '09:00', 'hora_fin' => '15:00'],
                    ['dia_semana' => 'domingo', 'hora_inicio' => '10:00', 'hora_fin' => '14:00'],
                ];

                return array_map(function ($horario) {
                    return array_merge($horario, [
                        'activo' => true,
                        'dia_completo' => false,
                        'observaciones' => 'Servicio express para oficinas'
                    ]);
                }, $horariosExpress);

            default:
                return array_map(function ($horario) {
                    return array_merge($horario, [
                        'activo' => true,
                        'dia_completo' => false,
                        'observaciones' => null
                    ]);
                }, $horariosBase);
        }
    }
}
