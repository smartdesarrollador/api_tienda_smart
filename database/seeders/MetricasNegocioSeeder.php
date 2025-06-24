<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MetricasNegocio;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class MetricasNegocioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Generar métricas para los últimos 30 días
        $fechas = collect();
        for ($i = 29; $i >= 0; $i--) {
            $fechas->push(Carbon::now()->subDays($i)->format('Y-m-d'));
        }

        foreach ($fechas as $fecha) {
            $esFinDeSemana = Carbon::parse($fecha)->isWeekend();
            $esDiaLaboral = !$esFinDeSemana;
            
            // Generar datos más realistas según el día
            $factorDia = $esFinDeSemana ? 1.4 : 1.0; // Más pedidos en fin de semana
            $factorAleatorio = mt_rand(80, 120) / 100; // Variación del ±20%

            $pedidosBase = 25;
            $totalPedidos = (int) ($pedidosBase * $factorDia * $factorAleatorio);
            $ticketPromedio = 32.50 + (mt_rand(-500, 800) / 100); // S/. 27.50 - S/. 40.50
            
            $metricas = [
                'fecha' => $fecha,
                'pedidos_totales' => $totalPedidos,
                'pedidos_entregados' => (int) ($totalPedidos * 0.92), // 92% entregados
                'pedidos_cancelados' => (int) ($totalPedidos * 0.08), // 8% cancelados
                'ventas_totales' => round($totalPedidos * $ticketPromedio, 2),
                'costo_envios' => round($totalPedidos * 5.50, 2),
                'nuevos_clientes' => mt_rand(3, 12),
                'clientes_recurrentes' => (int) ($totalPedidos * 0.65), // 65% clientes recurrentes
                'tiempo_promedio_entrega' => round(mt_rand(25, 45) + (mt_rand(0, 100) / 100), 2), // 25-45 minutos
                'productos_vendidos' => $totalPedidos * mt_rand(2, 4), // 2-4 productos por pedido
                'ticket_promedio' => round($ticketPromedio, 2),
                'productos_mas_vendidos' => json_encode([
                    'Hamburguesa Clásica' => mt_rand(5, 15),
                    'Hamburguesa BBQ' => mt_rand(3, 12),
                    'Combo Familiar' => mt_rand(2, 8),
                    'Pizza Personal' => mt_rand(1, 6),
                    'Pollo a la Brasa' => mt_rand(2, 10)
                ]),
                'zonas_mas_activas' => json_encode([
                    'zona-centro' => (int) ($totalPedidos * 0.45),
                    'zona-sur' => (int) ($totalPedidos * 0.35),
                    'zona-express' => (int) ($totalPedidos * 0.20)
                ]),
            ];

            MetricasNegocio::create($metricas);
        }
    }

    private function obtenerZonaMasActiva(bool $esFinDeSemana): string
    {
        if ($esFinDeSemana) {
            return ['zona-sur', 'zona-centro'][mt_rand(0, 1)];
        }
        return ['zona-centro', 'zona-express'][mt_rand(0, 1)];
    }

    private function obtenerProductoMasVendido(bool $esFinDeSemana): string
    {
        $productos = [
            'Hamburguesa Clásica',
            'Hamburguesa BBQ',
            'Combo Familiar',
            'Pizza Personal',
            'Pollo a la Brasa'
        ];
        
        return $productos[mt_rand(0, count($productos) - 1)];
    }

    private function obtenerHoraPico(bool $esFinDeSemana): string
    {
        if ($esFinDeSemana) {
            return ['19:00', '20:00', '21:00'][mt_rand(0, 2)];
        }
        return ['12:30', '13:00', '19:30', '20:00'][mt_rand(0, 3)];
    }

    private function generarPedidosPorHora(int $totalPedidos, bool $esFinDeSemana): array
    {
        $distribucion = [];
        $horas = range(11, 23); // 11 AM a 11 PM
        
        foreach ($horas as $hora) {
            $factor = 1.0;
            
            // Ajustar según horas pico
            if (in_array($hora, [12, 13])) { // Almuerzo
                $factor = $esFinDeSemana ? 1.2 : 1.8;
            } elseif (in_array($hora, [19, 20, 21])) { // Cena
                $factor = $esFinDeSemana ? 1.6 : 1.5;
            } elseif ($hora < 12 || $hora > 21) { // Horas bajas
                $factor = 0.3;
            }
            
            $pedidosHora = max(0, (int) ($totalPedidos * $factor * mt_rand(80, 120) / 100 / 8));
            $distribucion[sprintf('%02d:00', $hora)] = $pedidosHora;
        }
        
        return $distribucion;
    }

    private function generarObservaciones(string $fecha, int $totalPedidos, bool $esFinDeSemana): ?string
    {
        $observaciones = [];
        
        if ($totalPedidos > 40) {
            $observaciones[] = 'Día de alta demanda';
        }
        
        if ($totalPedidos < 15) {
            $observaciones[] = 'Día de baja demanda';
        }
        
        if ($esFinDeSemana) {
            $observaciones[] = 'Patrón típico de fin de semana';
        }
        
        // Eventos especiales aleatorios
        if (mt_rand(1, 10) === 1) {
            $eventos = [
                'Promoción especial activa',
                'Día lluvioso afectó entregas',
                'Nuevo producto lanzado',
                'Campaña en redes sociales'
            ];
            $observaciones[] = $eventos[mt_rand(0, count($eventos) - 1)];
        }
        
        return empty($observaciones) ? null : implode('. ', $observaciones);
    }
}
