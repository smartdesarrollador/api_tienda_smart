<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;

class MetodoEnvioController extends Controller
{
    /**
     * Obtener métodos de envío disponibles
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $metodosEnvio = [
                [
                    'id' => 1,
                    'nombre' => 'Envío Estándar',
                    'descripcion' => 'Entrega en 3-5 días hábiles',
                    'icono' => 'truck',
                    'tiempo_entrega_min' => 3,
                    'tiempo_entrega_max' => 5,
                    'activo' => true,
                    'costo_base' => 10.0,
                    'costo_por_kg' => 2.0
                ],
                [
                    'id' => 2,
                    'nombre' => 'Envío Express',
                    'descripcion' => 'Entrega en 1-2 días hábiles',
                    'icono' => 'lightning-bolt',
                    'tiempo_entrega_min' => 1,
                    'tiempo_entrega_max' => 2,
                    'activo' => true,
                    'costo_base' => 15.0,
                    'costo_por_kg' => 3.0
                ],
                [
                    'id' => 3,
                    'nombre' => 'Recojo en Tienda',
                    'descripcion' => 'Retira tu pedido en nuestra tienda física',
                    'icono' => 'store',
                    'tiempo_entrega_min' => 0,
                    'tiempo_entrega_max' => 1,
                    'activo' => true,
                    'costo_base' => 0.0,
                    'costo_por_kg' => 0.0,
                    'direccion_tienda' => 'Av. Principal 123, Miraflores, Lima'
                ],
                [
                    'id' => 4,
                    'nombre' => 'Envío Same Day',
                    'descripcion' => 'Entrega el mismo día (solo Lima Metropolitana)',
                    'icono' => 'clock',
                    'tiempo_entrega_min' => 0,
                    'tiempo_entrega_max' => 0,
                    'activo' => true,
                    'costo_base' => 25.0,
                    'costo_por_kg' => 5.0,
                    'restricciones' => ['Solo Lima Metropolitana', 'Pedidos antes de las 12:00 PM']
                ]
            ];

            // Filtrar por activos si se solicita
            if ($request->query('solo_activos', false)) {
                $metodosEnvio = array_filter($metodosEnvio, fn($metodo) => $metodo['activo']);
            }

            return response()->json([
                'success' => true,
                'data' => array_values($metodosEnvio)
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener métodos de envío: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Calcular costo de envío por ubicación
     */
    public function calcularCosto(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'metodo_envio_id' => 'required|integer|in:1,2,3,4',
                'distrito' => 'required|string|max:100',
                'provincia' => 'required|string|max:100',
                'departamento' => 'required|string|max:100',
                'peso_total' => 'required|numeric|min:0',
                'subtotal' => 'required|numeric|min:0'
            ]);

            $metodoId = $validated['metodo_envio_id'];
            $distrito = $validated['distrito'];
            $provincia = $validated['provincia'];
            $departamento = $validated['departamento'];
            $pesoTotal = $validated['peso_total'];
            $subtotal = $validated['subtotal'];

            // Obtener configuración del método de envío
            $metodosConfig = [
                1 => ['costo_base' => 10.0, 'costo_por_kg' => 2.0, 'multiplicador' => 1.0],
                2 => ['costo_base' => 15.0, 'costo_por_kg' => 3.0, 'multiplicador' => 1.5],
                3 => ['costo_base' => 0.0, 'costo_por_kg' => 0.0, 'multiplicador' => 0.0],
                4 => ['costo_base' => 25.0, 'costo_por_kg' => 5.0, 'multiplicador' => 2.0]
            ];

            $config = $metodosConfig[$metodoId];
            
            // Calcular costo base
            $costo = $config['costo_base'] + ($pesoTotal * $config['costo_por_kg']);

            // Aplicar multiplicadores por ubicación
            $costo = $this->aplicarMultiplicadorPorUbicacion($costo, $departamento, $provincia, $distrito);

            // Envío gratis para pedidos mayores a cierto monto (excepto Same Day)
            $envioGratis = false;
            if ($metodoId !== 4 && $subtotal >= 150) {
                $costo = 0.0;
                $envioGratis = true;
            }

            // Validar disponibilidad por ubicación
            $disponible = $this->validarDisponibilidadPorUbicacion($metodoId, $departamento, $provincia, $distrito);

            return response()->json([
                'success' => true,
                'data' => [
                    'metodo_envio_id' => $metodoId,
                    'costo' => round($costo, 2),
                    'disponible' => $disponible,
                    'envio_gratis' => $envioGratis,
                    'tiempo_estimado' => $this->calcularTiempoEstimado($metodoId, $departamento, $provincia),
                    'mensaje' => $disponible ? 'Método de envío disponible' : 'Método de envío no disponible para esta ubicación'
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos para cálculo de envío',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al calcular costo de envío: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener zonas de cobertura
     */
    public function zonasCobertura(): JsonResponse
    {
        try {
            $zonas = [
                'Lima Metropolitana' => [
                    'departamento' => 'Lima',
                    'provincias' => ['Lima'],
                    'distritos' => [
                        'Miraflores', 'San Isidro', 'Surco', 'Barranco', 'Chorrillos',
                        'La Molina', 'San Borja', 'Magdalena', 'Jesús María', 'Lince',
                        'Pueblo Libre', 'Breña', 'Lima Cercado', 'Rímac', 'San Miguel',
                        'Callao', 'Bellavista', 'La Perla', 'Carmen de la Legua'
                    ],
                    'metodos_disponibles' => [1, 2, 3, 4]
                ],
                'Lima Provincias' => [
                    'departamento' => 'Lima',
                    'provincias' => ['Huaura', 'Barranca', 'Cañete', 'Huarochirí'],
                    'metodos_disponibles' => [1, 2]
                ],
                'Principales Ciudades' => [
                    'departamentos' => ['Arequipa', 'Trujillo', 'Chiclayo', 'Piura', 'Iquitos', 'Cusco'],
                    'metodos_disponibles' => [1, 2]
                ],
                'Nacional' => [
                    'cobertura' => 'Todo el Perú',
                    'metodos_disponibles' => [1]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $zonas
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener zonas de cobertura: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener información detallada de un método de envío
     */
    public function show(int $id): JsonResponse
    {
        try {
            $metodosDetalle = [
                1 => [
                    'id' => 1,
                    'nombre' => 'Envío Estándar',
                    'descripcion' => 'Entrega en 3-5 días hábiles',
                    'descripcion_completa' => 'Nuestro servicio de envío estándar te garantiza la entrega de tu pedido en un plazo de 3 a 5 días hábiles. Ideal para compras que no requieren urgencia.',
                    'icono' => 'truck',
                    'tiempo_entrega' => '3-5 días hábiles',
                    'costo_base' => 10.0,
                    'caracteristicas' => [
                        'Seguimiento en tiempo real',
                        'Seguro incluido',
                        'Entrega hasta la puerta',
                        'Notificaciones SMS'
                    ],
                    'restricciones' => [
                        'No incluye días feriados',
                        'Horario de entrega: 9:00 AM - 6:00 PM'
                    ]
                ],
                2 => [
                    'id' => 2,
                    'nombre' => 'Envío Express',
                    'descripcion' => 'Entrega en 1-2 días hábiles',
                    'descripcion_completa' => 'Servicio express para cuando necesitas tu pedido rápidamente. Entrega garantizada en 1 a 2 días hábiles.',
                    'icono' => 'lightning-bolt',
                    'tiempo_entrega' => '1-2 días hábiles',
                    'costo_base' => 15.0,
                    'caracteristicas' => [
                        'Prioridad en despacho',
                        'Seguimiento premium',
                        'Seguro premium incluido',
                        'Notificaciones push'
                    ],
                    'restricciones' => [
                        'Pedidos antes de las 2:00 PM',
                        'Horario de entrega: 8:00 AM - 8:00 PM'
                    ]
                ],
                3 => [
                    'id' => 3,
                    'nombre' => 'Recojo en Tienda',
                    'descripcion' => 'Retira tu pedido en nuestra tienda física',
                    'descripcion_completa' => 'Evita costos de envío retirando tu pedido directamente en nuestra tienda física.',
                    'icono' => 'store',
                    'tiempo_entrega' => 'Disponible en 2-4 horas',
                    'costo_base' => 0.0,
                    'direccion_tienda' => 'Av. Principal 123, Miraflores, Lima',
                    'horarios_atencion' => 'Lunes a Domingo: 10:00 AM - 8:00 PM',
                    'caracteristicas' => [
                        'Sin costo adicional',
                        'Verificación inmediata del producto',
                        'Atención personalizada'
                    ]
                ],
                4 => [
                    'id' => 4,
                    'nombre' => 'Envío Same Day',
                    'descripcion' => 'Entrega el mismo día (solo Lima Metropolitana)',
                    'descripcion_completa' => 'Recibe tu pedido el mismo día. Servicio disponible solo para Lima Metropolitana.',
                    'icono' => 'clock',
                    'tiempo_entrega' => 'Mismo día',
                    'costo_base' => 25.0,
                    'caracteristicas' => [
                        'Entrega en 4-8 horas',
                        'Seguimiento en tiempo real',
                        'Servicio premium'
                    ],
                    'restricciones' => [
                        'Solo Lima Metropolitana',
                        'Pedidos antes de las 12:00 PM',
                        'Disponible de lunes a sábado'
                    ]
                ]
            ];

            if (!isset($metodosDetalle[$id])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Método de envío no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $metodosDetalle[$id]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener detalle del método de envío: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Aplicar multiplicador por ubicación
     */
    private function aplicarMultiplicadorPorUbicacion(float $costo, string $departamento, string $provincia, string $distrito): float
    {
        // Lima Metropolitana - sin recargo
        $distritosCentricos = ['Miraflores', 'San Isidro', 'Surco', 'Barranco', 'San Borja'];
        if (in_array($distrito, $distritosCentricos)) {
            return $costo;
        }

        // Lima Metropolitana - recargo mínimo
        $distritosLima = ['Chorrillos', 'La Molina', 'Magdalena', 'Jesús María', 'Lince', 'Pueblo Libre'];
        if (in_array($distrito, $distritosLima)) {
            return $costo + 5.0;
        }

        // Provincias de Lima
        if ($departamento === 'Lima' && $provincia !== 'Lima') {
            return $costo * 1.5;
        }

        // Principales ciudades
        $ciudadesPrincipales = ['Arequipa', 'Trujillo', 'Chiclayo', 'Piura', 'Cusco'];
        if (in_array($departamento, $ciudadesPrincipales)) {
            return $costo * 2.0;
        }

        // Resto del país
        return $costo * 2.5;
    }

    /**
     * Validar disponibilidad por ubicación
     */
    private function validarDisponibilidadPorUbicacion(int $metodoId, string $departamento, string $provincia, string $distrito): bool
    {
        // Same Day solo en Lima Metropolitana
        if ($metodoId === 4) {
            $distritosLima = [
                'Miraflores', 'San Isidro', 'Surco', 'Barranco', 'Chorrillos',
                'La Molina', 'San Borja', 'Magdalena', 'Jesús María', 'Lince'
            ];
            return in_array($distrito, $distritosLima);
        }

        // Recojo en tienda solo disponible localmente
        if ($metodoId === 3) {
            return $departamento === 'Lima' && $provincia === 'Lima';
        }

        // Express disponible en principales ciudades
        if ($metodoId === 2) {
            $departamentosExpress = ['Lima', 'Arequipa', 'Trujillo', 'Chiclayo', 'Piura', 'Cusco'];
            return in_array($departamento, $departamentosExpress);
        }

        // Estándar disponible en todo el país
        return true;
    }

    /**
     * Calcular tiempo estimado de entrega
     */
    private function calcularTiempoEstimado(int $metodoId, string $departamento, string $provincia): array
    {
        $tiempos = [
            1 => ['min' => 3, 'max' => 5], // Estándar
            2 => ['min' => 1, 'max' => 2], // Express
            3 => ['min' => 0, 'max' => 0], // Recojo en tienda
            4 => ['min' => 0, 'max' => 0]  // Same day
        ];

        $tiempo = $tiempos[$metodoId];

        // Ajustar tiempo según ubicación
        if ($departamento !== 'Lima') {
            $tiempo['min'] += 1;
            $tiempo['max'] += 2;
        }

        return $tiempo;
    }
} 