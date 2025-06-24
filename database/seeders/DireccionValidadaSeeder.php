<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DireccionValidada;
use App\Models\Direccion;
use App\Models\Distrito;
use App\Models\User;
use App\Models\ZonaReparto;
use Illuminate\Database\Seeder;

class DireccionValidadaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener distritos
        $lince = Distrito::where('nombre', 'Lince')->first();
        $sanBorja = Distrito::where('nombre', 'San Borja')->first();
        $miraflores = Distrito::where('nombre', 'Miraflores')->first();
        $jesusMaria = Distrito::where('nombre', 'Jesús María')->first();

        // Obtener zonas de reparto
        $zonaCentro = ZonaReparto::where('slug', 'zona-centro')->first();
        $zonaSur = ZonaReparto::where('slug', 'zona-sur')->first();

        // Obtener algunos usuarios para asociar direcciones
        $usuarios = User::take(4)->get();

        $direccionesData = [
            // Direcciones en Lince
            [
                'distrito' => $lince,
                'zona' => $zonaCentro,
                'usuario' => $usuarios[0] ?? null,
                'direccion' => 'Av. Arequipa 1234',
                'referencia' => 'Frente al parque Mariscal Castilla',
                'latitud' => -12.0918,
                'longitud' => -77.0351,
                'tipo_direccion' => 'residencial',
            ],
            [
                'distrito' => $lince,
                'zona' => $zonaCentro,
                'usuario' => $usuarios[1] ?? null,
                'direccion' => 'Jr. Inca Garcilaso de la Vega 890',
                'referencia' => 'Cerca del Hospital Rebagliati',
                'latitud' => -12.0925,
                'longitud' => -77.0340,
                'tipo_direccion' => 'comercial',
            ],

            // Direcciones en San Borja
            [
                'distrito' => $sanBorja,
                'zona' => $zonaSur,
                'usuario' => $usuarios[2] ?? null,
                'direccion' => 'Av. San Borja Norte 567',
                'referencia' => 'Edificio corporativo Torre San Borja',
                'latitud' => -12.1089,
                'longitud' => -76.9947,
                'tipo_direccion' => 'oficina',
            ],
            [
                'distrito' => $sanBorja,
                'zona' => $zonaSur,
                'usuario' => $usuarios[3] ?? null,
                'direccion' => 'Av. Aviación 2850',
                'referencia' => 'Centro Comercial La Rambla',
                'latitud' => -12.1105,
                'longitud' => -76.9935,
                'tipo_direccion' => 'comercial',
            ],

            // Direcciones en Miraflores
            [
                'distrito' => $miraflores,
                'zona' => $zonaSur,
                'usuario' => $usuarios[0] ?? null,
                'direccion' => 'Av. Larco 345',
                'referencia' => 'Cerca del Parque Kennedy',
                'latitud' => -12.1211,
                'longitud' => -77.0281,
                'tipo_direccion' => 'residencial',
            ],
            [
                'distrito' => $miraflores,
                'zona' => $zonaSur,
                'usuario' => $usuarios[1] ?? null,
                'direccion' => 'Av. Benavides 1678',
                'referencia' => 'Edificio Benavides Plaza',
                'latitud' => -12.1235,
                'longitud' => -77.0295,
                'tipo_direccion' => 'oficina',
            ],

            // Direcciones en Jesús María
            [
                'distrito' => $jesusMaria,
                'zona' => $zonaCentro,
                'usuario' => $usuarios[2] ?? null,
                'direccion' => 'Av. Brasil 456',
                'referencia' => 'Frente al Hospital Militar',
                'latitud' => -12.0736,
                'longitud' => -77.0504,
                'tipo_direccion' => 'residencial',
            ],
            [
                'distrito' => $jesusMaria,
                'zona' => $zonaCentro,
                'usuario' => $usuarios[3] ?? null,
                'direccion' => 'Jr. Cahuide 789',
                'referencia' => 'Parque Campo de Marte',
                'latitud' => -12.0745,
                'longitud' => -77.0515,
                'tipo_direccion' => 'residencial',
            ],
        ];

        foreach ($direccionesData as $direccionData) {
            // Crear la dirección base primero
            $direccion = Direccion::create([
                'user_id' => $direccionData['usuario']->id ?? 1, // Si no hay usuario, usar ID 1
                'distrito_id' => $direccionData['distrito']->id,
                'direccion' => $direccionData['direccion'],
                'referencia' => $direccionData['referencia'],
                'latitud' => $direccionData['latitud'],
                'longitud' => $direccionData['longitud'],
                'validada' => true,
                'predeterminada' => false,
                'alias' => $this->generarAlias($direccionData['tipo_direccion']),
                'instrucciones_entrega' => $this->generarInstrucciones($direccionData['tipo_direccion']),
            ]);

            // Crear la dirección validada
            DireccionValidada::create([
                'direccion_id' => $direccion->id,
                'zona_reparto_id' => $direccionData['zona']->id,
                'latitud' => $direccionData['latitud'],
                'longitud' => $direccionData['longitud'],
                'distancia_tienda_km' => $this->calcularDistancia($direccionData['latitud'], $direccionData['longitud']),
                'en_zona_cobertura' => true,
                'costo_envio_calculado' => $direccionData['zona']->costo_base_envio,
                'tiempo_entrega_estimado' => $direccionData['zona']->tiempo_entrega_estimado,
                'fecha_ultima_validacion' => now(),
                'observaciones_validacion' => $this->generarObservaciones($direccionData['tipo_direccion']),
            ]);
        }
    }

    private function generarAlias(string $tipo): string
    {
        $aliases = [
            'residencial' => ['Casa', 'Hogar', 'Residencia', 'Domicilio'],
            'oficina' => ['Oficina', 'Trabajo', 'Empresa', 'Corporativo'],
            'comercial' => ['Negocio', 'Local', 'Comercio', 'Tienda'],
        ];

        $opciones = $aliases[$tipo] ?? ['Dirección'];
        return $opciones[array_rand($opciones)];
    }

    private function generarInstrucciones(string $tipo): string
    {
        $instrucciones = [
            'residencial' => [
                'Tocar el timbre y esperar',
                'Casa de color blanco con jardín',
                'Segundo piso, departamento 2B',
                'Entrega en recepción si no hay nadie',
            ],
            'oficina' => [
                'Entregar en recepción del edificio',
                'Piso 5, área de administración',
                'Coordinar entrega con seguridad',
                'Horario de oficina: 9am a 6pm',
            ],
            'comercial' => [
                'Entregar en mostrador principal',
                'Preguntar por el encargado',
                'Entrada por puerta principal',
                'Verificar horarios de atención',
            ],
        ];

        $opciones = $instrucciones[$tipo] ?? ['Entregar según indicaciones'];
        return $opciones[array_rand($opciones)];
    }

    private function calcularDistancia(float $lat, float $lng): float
    {
        // Coordenadas aproximadas del centro de Lima (tienda base)
        $latTienda = -12.0464;
        $lngTienda = -77.0428;

        // Calcular distancia euclidiana aproximada en km
        $deltaLat = $lat - $latTienda;
        $deltaLng = $lng - $lngTienda;
        
        // Conversión aproximada: 1 grado ≈ 111 km
        $distanciaKm = sqrt(($deltaLat * 111) ** 2 + ($deltaLng * 111 * cos(deg2rad($lat))) ** 2);
        
        return round($distanciaKm, 2);
    }

    private function generarObservaciones(string $tipo): string
    {
        $observaciones = [
            'residencial' => 'Dirección residencial validada por geocoding',
            'oficina' => 'Edificio corporativo con acceso controlado',
            'comercial' => 'Local comercial con horarios específicos',
        ];

        return $observaciones[$tipo] ?? 'Dirección validada correctamente';
    }
}
