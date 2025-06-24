<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Direccion;
use App\Models\User;
use App\Models\Distrito;
use Illuminate\Database\Seeder;

class DireccionSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = User::where('rol', 'cliente')->get();
        $distritos = Distrito::all();

        foreach ($clientes as $cliente) {
            // Dirección principal
            $distrito = $distritos->random();
            Direccion::create([
                'user_id' => $cliente->id,
                'distrito_id' => $distrito->id,
                'direccion' => 'Av. Principal ' . rand(100, 999),
                'referencia' => 'Cerca del parque central',
                'codigo_postal' => '15' . rand(100, 999),
                'numero_exterior' => (string) rand(100, 999),
                'numero_interior' => rand(1, 3) === 1 ? (string) rand(1, 20) : null,
                'urbanizacion' => rand(1, 3) === 1 ? 'Urbanización ' . collect(['Los Jardines', 'Las Flores', 'San Carlos'])->random() : null,
                'latitud' => -12.0 + (rand(-500, 500) / 10000), // Lima aproximadamente
                'longitud' => -77.0 + (rand(-500, 500) / 10000),
                'predeterminada' => true,
                'validada' => true,
                'alias' => 'Casa',
                'instrucciones_entrega' => collect([
                    'Tocar el timbre dos veces',
                    'Llamar al llegar',
                    'Dejar con el portero',
                    null
                ])->random(),
            ]);

            // Dirección secundaria (opcional)
            if (rand(1, 100) <= 60) { // 60% de probabilidad
                $distrito2 = $distritos->random();
                Direccion::create([
                    'user_id' => $cliente->id,
                    'distrito_id' => $distrito2->id,
                    'direccion' => 'Jr. Secundaria ' . rand(100, 999),
                    'referencia' => 'Frente al mercado',
                    'codigo_postal' => '15' . rand(100, 999),
                    'numero_exterior' => (string) rand(100, 999),
                    'latitud' => -12.0 + (rand(-500, 500) / 10000),
                    'longitud' => -77.0 + (rand(-500, 500) / 10000),
                    'predeterminada' => false,
                    'validada' => rand(1, 10) <= 8, // 80% validadas
                    'alias' => collect(['Trabajo', 'Casa de mamá', 'Oficina'])->random(),
                    'instrucciones_entrega' => collect([
                        'Preguntar por recepción',
                        'Oficina del segundo piso',
                        null
                    ])->random(),
                ]);
            }
        }
    }
} 