<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pedido;
use App\Models\User;
use App\Models\MetodoPago;
use App\Models\ZonaReparto;
use App\Models\DireccionValidada;
use Illuminate\Database\Seeder;

class PedidoSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = User::where('rol', 'cliente')->get();
        $repartidores = User::where('rol', 'repartidor')->get();
        $zonasReparto = ZonaReparto::where('activo', true)->get();
        $direccionesValidadas = DireccionValidada::where('en_zona_cobertura', true)->get();

        // Cache de métodos de pago para evitar consultas repetidas
        $metodosPago = MetodoPago::activo()->get()->keyBy('slug');

        foreach ($clientes as $cliente) {
            // Crear entre 1 y 3 pedidos por cliente
            $cantidadPedidos = rand(1, 3);
            
            for ($i = 0; $i < $cantidadPedidos; $i++) {
                $tipoPago = collect(['contado', 'credito', 'transferencia', 'tarjeta', 'yape', 'plin'])->random();
                $total = rand(500, 8000);
                $estado = collect(['pendiente', 'aprobado', 'enviado', 'entregado', 'en_proceso'])->random();

                // Seleccionar zona y dirección
                $zonaReparto = $zonasReparto->random();
                $direccionValidada = $direccionesValidadas->random();
                
                // Asignar repartidor si el pedido está enviado o entregado
                $repartidor = null;
                if (in_array($estado, ['enviado', 'entregado']) && $repartidores->isNotEmpty()) {
                    $repartidor = $repartidores->random();
                }

                // Obtener método de pago correspondiente
                $metodoPago = $this->obtenerMetodoPago($tipoPago, $metodosPago);
                
                // Si es crédito, calcular cuotas
                $cuotas = null;
                $montoCuota = null;
                $interesTotal = null;
                
                if ($tipoPago === 'credito') {
                    $cuotas = collect([3, 6, 12, 18, 24])->random();
                    $tasaInteres = 0.08; // 8% anual
                    $interesTotal = ($total * $tasaInteres * $cuotas) / 12;
                    $montoCuota = ($total + $interesTotal) / $cuotas;
                }
                
                $descuentoTotal = $tipoPago === 'contado' ? $total * 0.05 : 0; // 5% descuento al contado
                
                // Calcular costo de envío según la zona
                $costoEnvio = $total > $zonaReparto->pedido_minimo ? $zonaReparto->costo_envio : $zonaReparto->costo_envio + $zonaReparto->costo_envio_adicional;
                
                Pedido::create([
                    'user_id' => $cliente->id,
                    'numero_pedido' => 'PED-' . date('Y') . '-' . date('md') . '-' . strtoupper(substr(uniqid(), -6)),
                    'metodo_pago_id' => $metodoPago?->id,
                    'zona_reparto_id' => $zonaReparto->id,
                    'direccion_validada_id' => $direccionValidada->id,
                    'repartidor_id' => $repartidor?->id,
                    'subtotal' => $total * 0.85, // Asumiendo que el 85% es subtotal
                    'costo_envio' => $costoEnvio,
                    'total' => $total,
                    'estado' => $estado,
                    'tipo_pago' => $tipoPago,
                    'tipo_entrega' => 'delivery',
                    'cuotas' => $cuotas,
                    'monto_cuota' => $montoCuota,
                    'interes_total' => $interesTotal,
                    'descuento_total' => $descuentoTotal,
                    'observaciones' => $this->generarObservacion($tipoPago, $estado),
                    'codigo_rastreo' => $estado === 'enviado' || $estado === 'entregado' ? 'TV' . rand(100000, 999999) : null,
                    'moneda' => 'PEN',
                    'canal_venta' => collect(['web', 'app', 'tienda_fisica'])->random(),
                    'tiempo_entrega_estimado' => rand($zonaReparto->tiempo_entrega_min, $zonaReparto->tiempo_entrega_max),
                    'fecha_entrega_programada' => $this->calcularFechaEntrega($zonaReparto),
                    'fecha_entrega_real' => $estado === 'entregado' ? now()->subHours(rand(1, 24)) : null,
                    'direccion_entrega' => 'Dirección de ejemplo ' . rand(100, 999),
                    'telefono_entrega' => '999999999',
                    'referencia_entrega' => 'Referencia de ejemplo',
                    'latitud_entrega' => $direccionValidada->latitud,
                    'longitud_entrega' => $direccionValidada->longitud,
                    'created_at' => now()->subDays(rand(1, 60)),
                ]);
            }
        }
    }

    private function calcularFechaEntrega(ZonaReparto $zona): \Carbon\Carbon
    {
        $tiempoPromedio = ($zona->tiempo_entrega_min + $zona->tiempo_entrega_max) / 2;
        return now()->addMinutes($tiempoPromedio);
    }

    private function generarInstruccionesEntrega(): ?string
    {
        $instrucciones = [
            'Tocar el timbre dos veces',
            'Llamar al llegar, no tocar el timbre',
            'Dejar en la puerta principal del edificio',
            'Preguntar por el departamento en recepción',
            'Contactar por WhatsApp al llegar',
            'Sin instrucciones especiales',
            null // Sin instrucciones
        ];
        
        return $instrucciones[array_rand($instrucciones)];
    }

    private function generarConfirmacionEntrega(): array
    {
        return [
            'recibido_por' => collect(['Cliente', 'Familiar', 'Portero', 'Vecino'])->random(),
            'firma_digital' => 'firma_' . uniqid() . '.png',
            'foto_entrega' => 'entrega_' . uniqid() . '.jpg',
            'comentario_repartidor' => collect([
                'Entrega exitosa',
                'Cliente satisfecho',
                'Entrega sin novedad',
                'Producto entregado en perfecto estado'
            ])->random(),
            'hora_confirmacion' => now()->format('H:i:s'),
            'calificacion_cliente' => rand(4, 5) // Rating de 4-5 estrellas
        ];
    }

    private function generarObservacion(string $tipoPago, string $estado): ?string
    {
        $observaciones = [
            'contado' => [
                'aprobado' => 'Pago confirmado al contado - Descuento aplicado',
                'pendiente' => 'Esperando confirmación de pago',
                'enviado' => 'Pedido enviado - Pago confirmado',
            ],
            'credito' => [
                'aprobado' => 'Crédito aprobado - Límite verificado',
                'pendiente' => 'Evaluando límite de crédito disponible',
                'en_proceso' => 'Preparando pedido - Crédito aprobado',
            ],
            'yape' => [
                'aprobado' => 'Pago confirmado vía Yape',
                'pendiente' => 'Esperando confirmación de Yape',
            ],
            'plin' => [
                'aprobado' => 'Pago confirmado vía Plin',
                'pendiente' => 'Esperando confirmación de Plin',
            ],
        ];

        return $observaciones[$tipoPago][$estado] ?? 'Pedido en proceso normal';
    }

    private function obtenerMetodoPago(string $tipoPago, $metodosPago): ?MetodoPago
    {
        // Mapear tipos de pago a slugs de métodos de pago
        $mapeoTipos = [
            'contado' => 'efectivo',
            'credito' => 'visa', // Usar tarjeta para créditos
            'transferencia' => collect(['transferencia-bcp', 'transferencia-interbank'])->random(),
            'tarjeta' => collect(['visa', 'mastercard'])->random(),
            'yape' => 'yape',
            'plin' => 'plin',
        ];

        $slug = $mapeoTipos[$tipoPago] ?? 'efectivo';
        
        return $metodosPago->get($slug) ?? $metodosPago->first();
    }
} 