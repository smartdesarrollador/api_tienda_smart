<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProgramacionEntrega;
use App\Models\User;
use App\Models\Pedido;
use App\Models\ZonaReparto;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ProgramacionEntregaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener repartidores y pedidos
        $repartidores = User::where('rol', 'repartidor')->get();
        $pedidosParaEntregar = Pedido::whereIn('estado', ['aprobado', 'en_proceso', 'enviado'])
            ->whereNotNull('repartidor_id')
            ->with(['zonaReparto', 'direccionValidada'])
            ->get();

        if ($repartidores->isEmpty() || $pedidosParaEntregar->isEmpty()) {
            return; // No crear programaciones si no hay datos suficientes
        }

        // Crear programaciones para hoy y mañana
        $fechas = [
            Carbon::today(),
            Carbon::tomorrow(),
        ];

        foreach ($fechas as $fecha) {
            $this->crearProgramacionesPorFecha($fecha, $repartidores, $pedidosParaEntregar);
        }
    }

    private function crearProgramacionesPorFecha(Carbon $fecha, $repartidores, $pedidos): void
    {
        // Turnos de trabajo típicos
        $turnos = [
            ['inicio' => '09:00', 'fin' => '14:00', 'nombre' => 'Mañana'],
            ['inicio' => '12:00', 'fin' => '17:00', 'nombre' => 'Tarde'],
            ['inicio' => '17:00', 'fin' => '22:00', 'nombre' => 'Noche'],
        ];

        foreach ($repartidores as $repartidor) {
            // Cada repartidor puede trabajar en 1-2 turnos por día
            $turnosAsignados = collect($turnos)->random(mt_rand(1, 2));

            foreach ($turnosAsignados as $turno) {
                $this->crearProgramacionTurno($fecha, $repartidor, $turno, $pedidos);
            }
        }
    }

    private function crearProgramacionTurno(Carbon $fecha, User $repartidor, array $turno, $pedidos): void
    {
        $horaInicio = Carbon::parse($fecha->format('Y-m-d') . ' ' . $turno['inicio']);
        $horaFin = Carbon::parse($fecha->format('Y-m-d') . ' ' . $turno['fin']);

        // Seleccionar pedidos para este repartidor y turno (2-8 pedidos por turno)
        $cantidadPedidos = mt_rand(2, 8);
        $pedidosSeleccionados = $pedidos->where('repartidor_id', $repartidor->id)
            ->take($cantidadPedidos);

        if ($pedidosSeleccionados->isEmpty()) {
            // Si no hay pedidos específicos, tomar algunos aleatorios
            $pedidosSeleccionados = $pedidos->random(min($cantidadPedidos, $pedidos->count()));
        }

        // Calcular métricas de la ruta
        $distanciaTotal = $this->calcularDistanciaTotal($pedidosSeleccionados);
        $tiempoEstimado = $this->calcularTiempoEstimado($pedidosSeleccionados, $distanciaTotal);
        $rutaOptimizada = $this->optimizarRuta($pedidosSeleccionados);

        ProgramacionEntrega::create([
            'pedido_id' => $pedidosSeleccionados->first()->id ?? 1, // Usar el primer pedido
            'repartidor_id' => $repartidor->id,
            'fecha_programada' => $fecha,
            'hora_inicio_ventana' => $horaInicio,
            'hora_fin_ventana' => $horaFin,
            'estado' => $this->determinarEstado($fecha),
            'orden_ruta' => mt_rand(1, 10),
            'notas_repartidor' => $this->generarObservaciones($turno, $pedidosSeleccionados->count()),
            'hora_salida' => $fecha->isToday() && mt_rand(1, 3) === 1 ? now()->subHours(mt_rand(1, 3)) : null,
            'hora_llegada' => $fecha->isToday() && mt_rand(1, 5) === 1 ? now()->subHours(mt_rand(0, 2)) : null,
            'motivo_fallo' => mt_rand(1, 20) === 1 ? 'Cliente no disponible' : null,
        ]);
    }

    private function calcularDistanciaTotal($pedidos): float
    {
        if ($pedidos->isEmpty()) {
            return 0.0;
        }

        // Simular cálculo de distancia entre puntos
        // En un sistema real, esto usaría APIs de mapas como Google Maps
        $distanciaBase = $pedidos->count() * mt_rand(100, 300) / 100; // 1-3 km por pedido
        $factorOptimizacion = 0.85; // 15% de optimización de ruta
        
        return round($distanciaBase * $factorOptimizacion, 2);
    }

    private function calcularTiempoEstimado($pedidos, float $distancia): int
    {
        if ($pedidos->isEmpty()) {
            return 0;
        }

        // Tiempo base por pedido + tiempo de viaje
        $tiempoPorPedido = 15; // 15 minutos promedio por entrega
        $tiempoViaje = $distancia * 3; // 3 minutos por km promedio en Lima
        $tiempoPreparacion = 10; // 10 minutos de preparación inicial
        
        return (int) ($tiempoPreparacion + ($pedidos->count() * $tiempoPorPedido) + $tiempoViaje);
    }

    private function optimizarRuta($pedidos): array
    {
        if ($pedidos->isEmpty()) {
            return [];
        }

        // Simulación de optimización de ruta
        // En un sistema real, esto usaría algoritmos de TSP (Traveling Salesman Problem)
        $ruta = [];
        
        foreach ($pedidos as $index => $pedido) {
            $ruta[] = [
                'orden' => $index + 1,
                'pedido_id' => $pedido->id,
                'direccion' => $pedido->direccion_entrega ?? 'Dirección no especificada',
                'latitud' => $pedido->latitud_entrega ?? null,
                'longitud' => $pedido->longitud_entrega ?? null,
                'tiempo_estimado' => mt_rand(10, 25), // 10-25 minutos por parada
                'ventana_entrega' => [
                    'inicio' => now()->addMinutes($index * 30)->format('H:i'),
                    'fin' => now()->addMinutes(($index * 30) + 45)->format('H:i'),
                ],
                'tipo_entrega' => mt_rand(1, 10) <= 8 ? 'domicilio' : 'oficina',
                'observaciones_entrega' => $pedido->instrucciones_entrega,
            ];
        }
        
        return $ruta;
    }

    private function determinarZonaPrincipal($pedidos): ?string
    {
        if ($pedidos->isEmpty()) {
            return null;
        }

        // Determinar la zona más frecuente en los pedidos
        $zonas = $pedidos->map(function ($pedido) {
            return $pedido->zonaReparto->nombre ?? 'Sin zona';
        })->countBy();

        return $zonas->keys()->first();
    }

    private function calcularPrioridad($pedidos): int
    {
        if ($pedidos->isEmpty()) {
            return 3; // Prioridad normal
        }

        // Prioridad basada en el total de pedidos y estado
        $totalPedidos = $pedidos->count();
        $pedidosUrgentes = $pedidos->filter(function ($pedido) {
            return $pedido->estado === 'enviado' || 
                   ($pedido->fecha_entrega_estimada && $pedido->fecha_entrega_estimada < now()->addHour());
        })->count();

        if ($pedidosUrgentes > $totalPedidos * 0.5) {
            return 1; // Alta prioridad
        } elseif ($totalPedidos > 6) {
            return 2; // Prioridad media-alta
        } elseif ($totalPedidos > 3) {
            return 3; // Prioridad normal
        } else {
            return 4; // Prioridad baja
        }
    }

    private function determinarEstado(Carbon $fecha): string
    {
        if ($fecha->isToday()) {
            return collect(['programado', 'en_ruta', 'entregado'])->random();
        }
        
        return 'programado';
    }

    private function generarObservaciones(array $turno, int $cantidadPedidos): ?string
    {
        $observaciones = [
            "Turno {$turno['nombre']} con {$cantidadPedidos} entregas",
            "Ruta optimizada para mínimo tiempo de entrega",
            "Considerar tráfico en horas pico",
            "Verificar combustible antes de iniciar",
            "Mantener contacto cada 30 minutos",
        ];

        return $observaciones[array_rand($observaciones)];
    }

    private function generarRestricciones(Carbon $fecha, array $turno): ?array
    {
        $restricciones = [];

        if ($fecha->isWeekend()) {
            $restricciones[] = 'Horarios de fin de semana - Mayor demanda';
        }

        if ($turno['nombre'] === 'Noche') {
            $restricciones[] = 'Turno nocturno - Precauciones extra de seguridad';
        }

        if (mt_rand(1, 10) === 1) {
            $restricciones[] = 'Posible lluvia - Tiempo de entrega extendido';
        }

        return empty($restricciones) ? null : $restricciones;
    }

    private function calcularIncentivos($pedidos, float $distancia): ?array
    {
        if ($pedidos->isEmpty()) {
            return null;
        }

        $incentivos = [];

        // Incentivo por cantidad de pedidos
        if ($pedidos->count() >= 6) {
            $incentivos['cantidad'] = [
                'descripcion' => 'Bono por alta productividad',
                'monto' => 15.00,
                'tipo' => 'fijo'
            ];
        }

        // Incentivo por distancia eficiente
        if ($distancia < $pedidos->count() * 1.5) {
            $incentivos['eficiencia'] = [
                'descripcion' => 'Bono por ruta eficiente',
                'monto' => 8.00,
                'tipo' => 'fijo'
            ];
        }

        // Incentivo por horario
        if (mt_rand(1, 5) === 1) {
            $incentivos['horario'] = [
                'descripcion' => 'Bono horario pico',
                'monto' => 5.00,
                'tipo' => 'por_entrega'
            ];
        }

        return empty($incentivos) ? null : $incentivos;
    }

    private function generarContactoEmergencia(): array
    {
        return [
            'supervisor' => '+51 999 123 456',
            'emergencias' => '+51 999 000 911',
            'soporte_tecnico' => '+51 999 456 789',
        ];
    }

    private function generarEquipoAsignado(User $repartidor): array
    {
        $vehiculos = ['moto', 'bicicleta', 'auto'];
        $vehiculo = $vehiculos[array_rand($vehiculos)];

        return [
            'vehiculo' => $vehiculo,
            'placa' => strtoupper(substr($repartidor->nombre ?? 'REP', 0, 2)) . mt_rand(100, 999),
            'gps_dispositivo' => 'GPS-' . mt_rand(1000, 9999),
            'uniforme' => true,
            'mochila_termica' => $vehiculo !== 'auto',
            'telefono_corporativo' => '+51 999 ' . mt_rand(100, 999) . ' ' . mt_rand(100, 999),
        ];
    }

    private function calcularGastosEstimados(float $distancia): array
    {
        return [
            'combustible' => round($distancia * 0.35, 2), // S/. 0.35 por km
            'mantenimiento' => round($distancia * 0.05, 2), // S/. 0.05 por km
            'comunicacion' => 2.00, // Fijo por turno
            'otros' => 1.50,
        ];
    }

    private function calcularComisionBase($pedidos): float
    {
        // S/. 4.00 por entrega base
        return $pedidos->count() * 4.00;
    }

    private function calcularComisionExtra($pedidos, array $turno): float
    {
        $extra = 0.0;

        // Extra por turno nocturno
        if ($turno['nombre'] === 'Noche') {
            $extra += $pedidos->count() * 1.50;
        }

        // Extra por cantidad
        if ($pedidos->count() > 5) {
            $extra += ($pedidos->count() - 5) * 2.00;
        }

        return $extra;
    }
}
