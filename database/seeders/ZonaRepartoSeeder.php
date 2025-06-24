<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ZonaReparto;
use Illuminate\Database\Seeder;

class ZonaRepartoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zonasReparto = [
            [
                'nombre' => 'Zona Centro',
                'slug' => 'zona-centro',
                'descripcion' => 'Zona central de Lima que incluye Lince y Jesús María',
                'costo_envio' => 5.00,
                'costo_envio_adicional' => 1.50,
                'tiempo_entrega_min' => 25,
                'tiempo_entrega_max' => 45,
                'pedido_minimo' => 15.00,
                'radio_cobertura_km' => 3.0,
                'coordenadas_centro' => '-12.0827,-77.0427',
                'activo' => true,
                'disponible_24h' => false,
                'orden' => 1,
                'color_mapa' => '#FF5722',
                'observaciones' => 'Zona con mayor demanda, horario extendido los fines de semana',
            ],
            [
                'nombre' => 'Zona Sur',
                'slug' => 'zona-sur',
                'descripcion' => 'Zona sur que incluye Miraflores y San Borja',
                'costo_envio' => 7.00,
                'costo_envio_adicional' => 2.00,
                'tiempo_entrega_min' => 30,
                'tiempo_entrega_max' => 55,
                'pedido_minimo' => 20.00,
                'radio_cobertura_km' => 4.0,
                'coordenadas_centro' => '-12.115,-77.0116',
                'activo' => true,
                'disponible_24h' => false,
                'orden' => 2,
                'color_mapa' => '#2196F3',
                'observaciones' => 'Zona residencial premium, clientes exigentes',
            ],
            [
                'nombre' => 'Zona Express',
                'slug' => 'zona-express',
                'descripcion' => 'Zona de entrega rápida para oficinas del centro',
                'costo_envio' => 8.00,
                'costo_envio_adicional' => 0.00,
                'tiempo_entrega_min' => 15,
                'tiempo_entrega_max' => 25,
                'pedido_minimo' => 25.00,
                'radio_cobertura_km' => 2.0,
                'coordenadas_centro' => '-12.0736,-77.0504',
                'activo' => true,
                'disponible_24h' => false,
                'orden' => 3,
                'color_mapa' => '#4CAF50',
                'observaciones' => 'Servicio express para horario de oficina (9am-6pm)',
            ],
        ];

        foreach ($zonasReparto as $zona) {
            ZonaReparto::create($zona);
        }
    }
}
