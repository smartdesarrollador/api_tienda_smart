<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ZonaReparto;
use App\Models\Distrito;
use Illuminate\Database\Seeder;

class ZonaDistritoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener zonas y distritos
        $zonaCentro = ZonaReparto::where('slug', 'zona-centro')->first();
        $zonaSur = ZonaReparto::where('slug', 'zona-sur')->first();
        $zonaExpress = ZonaReparto::where('slug', 'zona-express')->first();

        // Obtener distritos
        $lince = Distrito::where('nombre', 'Lince')->first();
        $sanBorja = Distrito::where('nombre', 'San Borja')->first();
        $miraflores = Distrito::where('nombre', 'Miraflores')->first();
        $jesusMaria = Distrito::where('nombre', 'Jesús María')->first();

        // Relacionar zonas con distritos
        if ($zonaCentro && $lince && $jesusMaria) {
            // Zona Centro: Lince y Jesús María
            $zonaCentro->distritos()->attach($lince->id, [
                'activo' => true,
                'prioridad' => 1,
                'costo_envio_personalizado' => null, // Usar costo base de la zona
                'tiempo_adicional' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $zonaCentro->distritos()->attach($jesusMaria->id, [
                'activo' => true,
                'prioridad' => 2,
                'costo_envio_personalizado' => null,
                'tiempo_adicional' => 5, // 5 minutos adicionales
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($zonaSur && $miraflores && $sanBorja) {
            // Zona Sur: Miraflores y San Borja
            $zonaSur->distritos()->attach($miraflores->id, [
                'activo' => true,
                'prioridad' => 1,
                'costo_envio_personalizado' => 6.50, // Costo personalizado para distrito premium
                'tiempo_adicional' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $zonaSur->distritos()->attach($sanBorja->id, [
                'activo' => true,
                'prioridad' => 1,
                'costo_envio_personalizado' => 7.50, // Distrito empresarial
                'tiempo_adicional' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($zonaExpress && $lince && $jesusMaria) {
            // Zona Express: Lince y Jesús María (solo horario de oficina)
            $zonaExpress->distritos()->attach($lince->id, [
                'activo' => true,
                'prioridad' => 1,
                'costo_envio_personalizado' => 8.00, // Servicio express premium
                'tiempo_adicional' => -10, // 10 minutos menos por ser express
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $zonaExpress->distritos()->attach($jesusMaria->id, [
                'activo' => true,
                'prioridad' => 1,
                'costo_envio_personalizado' => 8.00,
                'tiempo_adicional' => -10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // También agregar San Isidro para zona express (oficinas)
            $sanIsidro = Distrito::where('nombre', 'San Isidro')->first();
            if ($sanIsidro) {
                $zonaExpress->distritos()->attach($sanIsidro->id, [
                    'activo' => true,
                    'prioridad' => 1,
                    'costo_envio_personalizado' => 9.00, // San Isidro premium
                    'tiempo_adicional' => -5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Agregar Miraflores también a zona express para oficinas
            if ($miraflores) {
                $zonaExpress->distritos()->attach($miraflores->id, [
                    'activo' => true,
                    'prioridad' => 2, // Prioridad secundaria en express
                    'costo_envio_personalizado' => 9.50,
                    'tiempo_adicional' => -5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
