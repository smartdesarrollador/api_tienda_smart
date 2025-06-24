<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Pago;
use App\Models\CuotaCredito;
use App\Models\Comentario;
use App\Models\Notificacion;
use App\Models\Favorito;
use App\Models\Categoria;
use App\Models\Cupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Obtener resumen general del dashboard
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'periodo' => 'nullable|in:hoy,semana,mes,trimestre,año',
                'fecha_desde' => 'nullable|date',
                'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
                'incluir_cache' => 'nullable|boolean'
            ]);

            $periodo = $request->get('periodo', 'mes');
            $incluirCache = $request->get('incluir_cache', true);
            $cacheKey = "dashboard_resumen_{$periodo}";

            if ($incluirCache && Cache::has($cacheKey)) {
                return response()->json([
                    'success' => true,
                    'data' => Cache::get($cacheKey),
                    'cached' => true,
                    'cache_expires_at' => Cache::get($cacheKey . '_expires')
                ]);
            }

            // Calcular fechas según período
            $fechas = $this->calcularFechasPeriodo($periodo, $request);

            // KPIs principales
            $kpis = $this->obtenerKPIsPrincipales($fechas);

            // Estadísticas de ventas
            $ventas = $this->obtenerEstadisticasVentas($fechas);

            // Estadísticas de productos
            $productos = $this->obtenerEstadisticasProductos($fechas);

            // Estadísticas de usuarios
            $usuarios = $this->obtenerEstadisticasUsuarios($fechas);

            // Estadísticas financieras
            $financieras = $this->obtenerEstadisticasFinancieras($fechas);

            // Actividad reciente
            $actividad = $this->obtenerActividadReciente();

            // Alertas y notificaciones
            $alertas = $this->obtenerAlertas();

            $data = [
                'periodo' => $periodo,
                'fechas' => $fechas,
                'kpis' => $kpis,
                'ventas' => $ventas,
                'productos' => $productos,
                'usuarios' => $usuarios,
                'financieras' => $financieras,
                'actividad_reciente' => $actividad,
                'alertas' => $alertas,
                'ultima_actualizacion' => now()->toISOString()
            ];

            // Guardar en cache por 15 minutos
            if ($incluirCache) {
                Cache::put($cacheKey, $data, 900); // 15 minutos
                Cache::put($cacheKey . '_expires', now()->addMinutes(15)->toISOString(), 900);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'cached' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener resumen del dashboard: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los datos del dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de ventas detalladas
     */
    public function ventas(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'periodo' => 'nullable|in:hoy,semana,mes,trimestre,año',
                'fecha_desde' => 'nullable|date',
                'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
                'agrupar_por' => 'nullable|in:dia,semana,mes,trimestre',
                'moneda' => 'nullable|in:PEN,USD,EUR'
            ]);

            $fechas = $this->calcularFechasPeriodo($request->get('periodo', 'mes'), $request);
            $agruparPor = $request->get('agrupar_por', 'dia');
            $moneda = $request->get('moneda', 'PEN');

            // Ventas por período
            $ventasPorPeriodo = $this->obtenerVentasPorPeriodo($fechas, $agruparPor, $moneda);

            // Top productos vendidos
            $topProductos = $this->obtenerTopProductosVendidos($fechas, $moneda);

            // Ventas por canal
            $ventasPorCanal = $this->obtenerVentasPorCanal($fechas, $moneda);

            // Ventas por método de pago
            $ventasPorMetodoPago = $this->obtenerVentasPorMetodoPago($fechas, $moneda);

            // Análisis de conversión
            $conversion = $this->obtenerAnalisisConversion($fechas);

            // Comparación con período anterior
            $comparacion = $this->obtenerComparacionPeriodoAnterior($fechas, $moneda);

            return response()->json([
                'success' => true,
                'data' => [
                    'periodo' => $request->get('periodo', 'mes'),
                    'fechas' => $fechas,
                    'moneda' => $moneda,
                    'ventas_por_periodo' => $ventasPorPeriodo,
                    'top_productos' => $topProductos,
                    'ventas_por_canal' => $ventasPorCanal,
                    'ventas_por_metodo_pago' => $ventasPorMetodoPago,
                    'analisis_conversion' => $conversion,
                    'comparacion_periodo_anterior' => $comparacion
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de ventas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas de ventas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de productos
     */
    public function productos(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'categoria_id' => 'nullable|exists:categorias,id',
                'incluir_variaciones' => 'nullable|boolean',
                'ordenar_por' => 'nullable|in:ventas,stock,precio,calificacion,favoritos'
            ]);

            $categoriaId = $request->get('categoria_id');
            $incluirVariaciones = $request->get('incluir_variaciones', true);
            $ordenarPor = $request->get('ordenar_por', 'ventas');

            // Resumen general de productos
            $resumenGeneral = $this->obtenerResumenProductos($categoriaId);

            // Productos más vendidos
            $masVendidos = $this->obtenerProductosMasVendidos($categoriaId, $ordenarPor);

            // Productos con bajo stock
            $bajoStock = $this->obtenerProductosBajoStock($categoriaId);

            // Productos mejor calificados
            $mejorCalificados = $this->obtenerProductosMejorCalificados($categoriaId);

            // Análisis de categorías
            $analisisCategorias = $this->obtenerAnalisisCategorias();

            // Productos más favoriteados
            $masFavoriteados = $this->obtenerProductosMasFavoriteados($categoriaId);

            // Tendencias de precios
            $tendenciasPrecios = $this->obtenerTendenciasPrecios($categoriaId);

            return response()->json([
                'success' => true,
                'data' => [
                    'resumen_general' => $resumenGeneral,
                    'mas_vendidos' => $masVendidos,
                    'bajo_stock' => $bajoStock,
                    'mejor_calificados' => $mejorCalificados,
                    'analisis_categorias' => $analisisCategorias,
                    'mas_favoriteados' => $masFavoriteados,
                    'tendencias_precios' => $tendenciasPrecios,
                    'filtros_aplicados' => [
                        'categoria_id' => $categoriaId,
                        'incluir_variaciones' => $incluirVariaciones,
                        'ordenar_por' => $ordenarPor
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de productos: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas de productos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de usuarios y clientes
     */
    public function usuarios(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'periodo' => 'nullable|in:hoy,semana,mes,trimestre,año',
                'rol' => 'nullable|in:cliente,administrador,vendedor,soporte,repartidor',
                'incluir_inactivos' => 'nullable|boolean'
            ]);

            $periodo = $request->get('periodo', 'mes');
            $rol = $request->get('rol');
            $incluirInactivos = $request->get('incluir_inactivos', false);

            $fechas = $this->calcularFechasPeriodo($periodo, $request);

            // Resumen general de usuarios
            $resumenGeneral = $this->obtenerResumenUsuarios($fechas, $rol, $incluirInactivos);

            // Nuevos registros por período
            $nuevosRegistros = $this->obtenerNuevosRegistrosPorPeriodo($fechas, $rol);

            // Usuarios más activos
            $usuariosMasActivos = $this->obtenerUsuariosMasActivos($fechas, $rol);

            // Análisis de comportamiento
            $comportamiento = $this->obtenerAnalisisComportamiento($fechas, $rol);

            // Distribución geográfica
            $distribucionGeografica = $this->obtenerDistribucionGeografica($rol);

            // Análisis de retención
            $retencion = $this->obtenerAnalisisRetencion($fechas, $rol);

            return response()->json([
                'success' => true,
                'data' => [
                    'periodo' => $periodo,
                    'fechas' => $fechas,
                    'resumen_general' => $resumenGeneral,
                    'nuevos_registros' => $nuevosRegistros,
                    'usuarios_mas_activos' => $usuariosMasActivos,
                    'analisis_comportamiento' => $comportamiento,
                    'distribucion_geografica' => $distribucionGeografica,
                    'analisis_retencion' => $retencion,
                    'filtros_aplicados' => [
                        'rol' => $rol,
                        'incluir_inactivos' => $incluirInactivos
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de usuarios: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas de usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener métricas financieras
     */
    public function financieras(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'periodo' => 'nullable|in:hoy,semana,mes,trimestre,año',
                'moneda' => 'nullable|in:PEN,USD,EUR',
                'incluir_proyecciones' => 'nullable|boolean'
            ]);

            $periodo = $request->get('periodo', 'mes');
            $moneda = $request->get('moneda', 'PEN');
            $incluirProyecciones = $request->get('incluir_proyecciones', true);

            $fechas = $this->calcularFechasPeriodo($periodo, $request);

            // Resumen financiero
            $resumenFinanciero = $this->obtenerResumenFinanciero($fechas, $moneda);

            // Flujo de caja
            $flujoCaja = $this->obtenerFlujoCaja($fechas, $moneda);

            // Análisis de créditos
            $analisisCreditos = $this->obtenerAnalisisCreditos($fechas, $moneda);

            // Métodos de pago más utilizados
            $metodosPago = $this->obtenerMetodosPagoMasUtilizados($fechas, $moneda);

            // Análisis de cupones
            $analisisCupones = $this->obtenerAnalisisCupones($fechas, $moneda);

            // Proyecciones (si se solicitan)
            $proyecciones = null;
            if ($incluirProyecciones) {
                $proyecciones = $this->obtenerProyeccionesFinancieras($fechas, $moneda);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'periodo' => $periodo,
                    'fechas' => $fechas,
                    'moneda' => $moneda,
                    'resumen_financiero' => $resumenFinanciero,
                    'flujo_caja' => $flujoCaja,
                    'analisis_creditos' => $analisisCreditos,
                    'metodos_pago' => $metodosPago,
                    'analisis_cupones' => $analisisCupones,
                    'proyecciones' => $proyecciones
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener métricas financieras: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las métricas financieras',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener alertas y notificaciones del sistema
     */
    public function alertas(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tipo' => 'nullable|in:stock,pagos,sistema,usuarios,ventas',
                'prioridad' => 'nullable|in:alta,media,baja',
                'solo_activas' => 'nullable|boolean'
            ]);

            $tipo = $request->get('tipo');
            $prioridad = $request->get('prioridad');
            $soloActivas = $request->get('solo_activas', true);

            $alertas = $this->obtenerAlertas($tipo, $prioridad, $soloActivas);

            return response()->json([
                'success' => true,
                'data' => $alertas,
                'filtros_aplicados' => [
                    'tipo' => $tipo,
                    'prioridad' => $prioridad,
                    'solo_activas' => $soloActivas
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener alertas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las alertas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener actividad reciente del sistema
     */
    public function actividad(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limite' => 'nullable|integer|min:1|max:100',
                'tipo' => 'nullable|in:pedidos,pagos,usuarios,productos,comentarios',
                'horas' => 'nullable|integer|min:1|max:168' // máx 1 semana
            ]);

            $limite = $request->get('limite', 20);
            $tipo = $request->get('tipo');
            $horas = $request->get('horas', 24);

            $actividad = $this->obtenerActividadReciente($limite, $tipo, $horas);

            return response()->json([
                'success' => true,
                'data' => $actividad,
                'filtros_aplicados' => [
                    'limite' => $limite,
                    'tipo' => $tipo,
                    'horas' => $horas
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener actividad reciente: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la actividad reciente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar cache del dashboard
     */
    public function limpiarCache(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tipo' => 'nullable|in:todo,resumen,ventas,productos,usuarios,financieras'
            ]);

            $tipo = $request->get('tipo', 'todo');
            $cacheLimpiado = [];

            if ($tipo === 'todo') {
                $patrones = ['dashboard_resumen_*', 'dashboard_ventas_*', 'dashboard_productos_*', 'dashboard_usuarios_*', 'dashboard_financieras_*'];
                foreach ($patrones as $patron) {
                    Cache::forget($patron);
                    $cacheLimpiado[] = $patron;
                }
            } else {
                $cacheKey = "dashboard_{$tipo}_*";
                Cache::forget($cacheKey);
                $cacheLimpiado[] = $cacheKey;
            }

            Log::info('Cache del dashboard limpiado', [
                'tipo' => $tipo,
                'cache_limpiado' => $cacheLimpiado,
                'cleaned_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cache del dashboard limpiado exitosamente',
                'cache_limpiado' => $cacheLimpiado
            ]);

        } catch (\Exception $e) {
            Log::error('Error al limpiar cache del dashboard: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar el cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Métodos privados para cálculos específicos

    private function calcularFechasPeriodo(string $periodo, Request $request): array
    {
        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            return [
                'desde' => Carbon::parse($request->fecha_desde)->startOfDay(),
                'hasta' => Carbon::parse($request->fecha_hasta)->endOfDay()
            ];
        }

        $ahora = Carbon::now();
        
        return match($periodo) {
            'hoy' => [
                'desde' => $ahora->copy()->startOfDay(),
                'hasta' => $ahora->copy()->endOfDay()
            ],
            'semana' => [
                'desde' => $ahora->copy()->startOfWeek(),
                'hasta' => $ahora->copy()->endOfWeek()
            ],
            'mes' => [
                'desde' => $ahora->copy()->startOfMonth(),
                'hasta' => $ahora->copy()->endOfMonth()
            ],
            'trimestre' => [
                'desde' => $ahora->copy()->startOfQuarter(),
                'hasta' => $ahora->copy()->endOfQuarter()
            ],
            'año' => [
                'desde' => $ahora->copy()->startOfYear(),
                'hasta' => $ahora->copy()->endOfYear()
            ],
            default => [
                'desde' => $ahora->copy()->startOfMonth(),
                'hasta' => $ahora->copy()->endOfMonth()
            ]
        };
    }

    private function obtenerKPIsPrincipales(array $fechas): array
    {
        // Total de ventas
        $totalVentas = Pedido::whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
            ->whereIn('estado', ['entregado', 'enviado'])
            ->sum('total');

        // Número de pedidos
        $numeroPedidos = Pedido::whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
            ->count();

        // Nuevos clientes
        $nuevosClientes = User::where('rol', 'cliente')
            ->whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
            ->count();

        // Ticket promedio
        $ticketPromedio = $numeroPedidos > 0 ? $totalVentas / $numeroPedidos : 0;

        // Productos vendidos
        $productosVendidos = DetallePedido::whereHas('pedido', function ($query) use ($fechas) {
            $query->whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
                  ->whereIn('estado', ['entregado', 'enviado']);
        })->sum('cantidad');

        // Tasa de conversión (pedidos completados vs total)
        $pedidosCompletados = Pedido::whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
            ->whereIn('estado', ['entregado'])
            ->count();
        
        $tasaConversion = $numeroPedidos > 0 ? ($pedidosCompletados / $numeroPedidos) * 100 : 0;

        return [
            'total_ventas' => round($totalVentas, 2),
            'numero_pedidos' => $numeroPedidos,
            'nuevos_clientes' => $nuevosClientes,
            'ticket_promedio' => round($ticketPromedio, 2),
            'productos_vendidos' => $productosVendidos,
            'tasa_conversion' => round($tasaConversion, 2)
        ];
    }

    private function obtenerEstadisticasVentas(array $fechas): array
    {
        // Ventas por estado
        $ventasPorEstado = Pedido::whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
            ->select('estado', DB::raw('count(*) as total'), DB::raw('sum(total) as monto'))
            ->groupBy('estado')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->estado => [
                    'cantidad' => $item->total,
                    'monto' => round($item->monto, 2)
                ]];
            });

        // Ventas por canal
        $ventasPorCanal = Pedido::whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
            ->select('canal_venta', DB::raw('count(*) as total'), DB::raw('sum(total) as monto'))
            ->groupBy('canal_venta')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->canal_venta => [
                    'cantidad' => $item->total,
                    'monto' => round($item->monto, 2)
                ]];
            });

        // Tendencia diaria
        $tendenciaDiaria = Pedido::whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
            ->whereIn('estado', ['entregado', 'enviado'])
            ->select(DB::raw('DATE(created_at) as fecha'), DB::raw('count(*) as pedidos'), DB::raw('sum(total) as ventas'))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get()
            ->map(function ($item) {
                return [
                    'fecha' => $item->fecha,
                    'pedidos' => $item->pedidos,
                    'ventas' => round($item->ventas, 2)
                ];
            });

        return [
            'ventas_por_estado' => $ventasPorEstado,
            'ventas_por_canal' => $ventasPorCanal,
            'tendencia_diaria' => $tendenciaDiaria
        ];
    }

    private function obtenerEstadisticasProductos(array $fechas): array
    {
        // Total de productos
        $totalProductos = Producto::count();
        $productosActivos = Producto::where('activo', true)->count();
        $productosDestacados = Producto::where('destacado', true)->count();

        // Productos con bajo stock
        $productosBajoStock = Producto::where('stock', '<=', 10)->count();

        // Productos más vendidos en el período
        $masVendidos = DetallePedido::whereHas('pedido', function ($query) use ($fechas) {
            $query->whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
                  ->whereIn('estado', ['entregado', 'enviado']);
        })
        ->select('producto_id', DB::raw('sum(cantidad) as total_vendido'))
        ->with('producto:id,nombre,precio,imagen_principal')
        ->groupBy('producto_id')
        ->orderBy('total_vendido', 'desc')
        ->limit(5)
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->producto->id,
                'nombre' => $item->producto->nombre,
                'precio' => $item->producto->precio,
                'imagen_principal' => $item->producto->imagen_principal,
                'cantidad_vendida' => $item->total_vendido
            ];
        });

        return [
            'total_productos' => $totalProductos,
            'productos_activos' => $productosActivos,
            'productos_destacados' => $productosDestacados,
            'productos_bajo_stock' => $productosBajoStock,
            'mas_vendidos' => $masVendidos
        ];
    }

    private function obtenerEstadisticasUsuarios(array $fechas): array
    {
        // Total de usuarios por rol
        $usuariosPorRol = User::select('rol', DB::raw('count(*) as total'))
            ->groupBy('rol')
            ->pluck('total', 'rol');

        // Nuevos usuarios en el período
        $nuevosUsuarios = User::whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
            ->select('rol', DB::raw('count(*) as total'))
            ->groupBy('rol')
            ->pluck('total', 'rol');

        // Usuarios activos (con pedidos en el período)
        $usuariosActivos = User::whereHas('pedidos', function ($query) use ($fechas) {
            $query->whereBetween('created_at', [$fechas['desde'], $fechas['hasta']]);
        })->count();

        // Top clientes por compras
        $topClientes = User::where('rol', 'cliente')
            ->withSum(['pedidos' => function ($query) use ($fechas) {
                $query->whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
                      ->whereIn('estado', ['entregado', 'enviado']);
            }], 'total')
            ->orderBy('pedidos_sum_total', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'email', 'pedidos_sum_total'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'email' => $user->email,
                    'total_compras' => round($user->pedidos_sum_total ?? 0, 2)
                ];
            });

        return [
            'usuarios_por_rol' => $usuariosPorRol,
            'nuevos_usuarios' => $nuevosUsuarios,
            'usuarios_activos' => $usuariosActivos,
            'top_clientes' => $topClientes
        ];
    }

    private function obtenerEstadisticasFinancieras(array $fechas): array
    {
        // Ingresos totales
        $ingresosTotales = Pago::where('estado', 'pagado')
            ->whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
            ->sum('monto');

        // Pagos por método
        $pagosPorMetodo = Pago::where('estado', 'pagado')
            ->whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
            ->select('metodo_pago', DB::raw('count(*) as cantidad'), DB::raw('sum(monto) as total'))
            ->groupBy('metodo_pago')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->metodo_pago => [
                    'cantidad' => $item->cantidad,
                    'total' => round($item->total, 2)
                ]];
            });

        // Créditos pendientes
        $creditosPendientes = CuotaCredito::where('estado', 'pendiente')
            ->sum('monto');

        // Créditos vencidos
        $creditosVencidos = CuotaCredito::where('estado', 'atrasado')
            ->sum('monto');

        return [
            'ingresos_totales' => round($ingresosTotales, 2),
            'pagos_por_metodo' => $pagosPorMetodo,
            'creditos_pendientes' => round($creditosPendientes, 2),
            'creditos_vencidos' => round($creditosVencidos, 2)
        ];
    }

    private function obtenerActividadReciente(int $limite = 10, ?string $tipo = null, int $horas = 24): array
    {
        $actividades = collect();
        $fechaLimite = Carbon::now()->subHours($horas);

        // Nuevos pedidos
        if (!$tipo || $tipo === 'pedidos') {
            $pedidos = Pedido::where('created_at', '>=', $fechaLimite)
                ->with('usuario:id,name')
                ->orderBy('created_at', 'desc')
                ->limit($limite)
                ->get()
                ->map(function ($pedido) {
                    return [
                        'tipo' => 'pedido',
                        'titulo' => "Nuevo pedido #{$pedido->id}",
                        'descripcion' => "Pedido de {$pedido->usuario->name} por S/ {$pedido->total}",
                        'fecha' => $pedido->created_at,
                        'icono' => 'shopping-cart',
                        'color' => 'blue'
                    ];
                });
            $actividades = $actividades->merge($pedidos);
        }

        // Nuevos usuarios
        if (!$tipo || $tipo === 'usuarios') {
            $usuarios = User::where('created_at', '>=', $fechaLimite)
                ->orderBy('created_at', 'desc')
                ->limit($limite)
                ->get()
                ->map(function ($usuario) {
                    return [
                        'tipo' => 'usuario',
                        'titulo' => "Nuevo usuario registrado",
                        'descripcion' => "{$usuario->name} se registró como {$usuario->rol}",
                        'fecha' => $usuario->created_at,
                        'icono' => 'user-plus',
                        'color' => 'green'
                    ];
                });
            $actividades = $actividades->merge($usuarios);
        }

        // Nuevos comentarios
        if (!$tipo || $tipo === 'comentarios') {
            $comentarios = Comentario::where('created_at', '>=', $fechaLimite)
                ->with(['usuario:id,name', 'producto:id,nombre'])
                ->orderBy('created_at', 'desc')
                ->limit($limite)
                ->get()
                ->map(function ($comentario) {
                    return [
                        'tipo' => 'comentario',
                        'titulo' => "Nuevo comentario",
                        'descripcion' => "{$comentario->usuario->name} comentó en {$comentario->producto->nombre}",
                        'fecha' => $comentario->created_at,
                        'icono' => 'message-circle',
                        'color' => 'yellow'
                    ];
                });
            $actividades = $actividades->merge($comentarios);
        }

        return $actividades->sortByDesc('fecha')->take($limite)->values()->all();
    }

    private function obtenerAlertas(?string $tipo = null, ?string $prioridad = null, bool $soloActivas = true): array
    {
        $alertas = collect();

        // Productos con bajo stock
        if (!$tipo || $tipo === 'stock') {
            $productosBajoStock = Producto::where('stock', '<=', 10)
                ->where('activo', true)
                ->count();
            
            if ($productosBajoStock > 0) {
                $alertas->push([
                    'tipo' => 'stock',
                    'prioridad' => $productosBajoStock > 20 ? 'alta' : 'media',
                    'titulo' => 'Productos con bajo stock',
                    'descripcion' => "{$productosBajoStock} productos tienen stock menor a 10 unidades",
                    'cantidad' => $productosBajoStock,
                    'accion' => 'Revisar inventario',
                    'icono' => 'alert-triangle',
                    'color' => 'orange'
                ]);
            }
        }

        // Pagos atrasados
        if (!$tipo || $tipo === 'pagos') {
            $pagosAtrasados = CuotaCredito::where('estado', 'atrasado')->count();
            
            if ($pagosAtrasados > 0) {
                $alertas->push([
                    'tipo' => 'pagos',
                    'prioridad' => 'alta',
                    'titulo' => 'Pagos atrasados',
                    'descripcion' => "{$pagosAtrasados} cuotas de crédito están atrasadas",
                    'cantidad' => $pagosAtrasados,
                    'accion' => 'Gestionar cobranza',
                    'icono' => 'credit-card',
                    'color' => 'red'
                ]);
            }
        }

        // Comentarios pendientes de moderación
        if (!$tipo || $tipo === 'sistema') {
            $comentariosPendientes = Comentario::where('aprobado', false)->count();
            
            if ($comentariosPendientes > 0) {
                $alertas->push([
                    'tipo' => 'sistema',
                    'prioridad' => 'media',
                    'titulo' => 'Comentarios pendientes',
                    'descripcion' => "{$comentariosPendientes} comentarios esperan moderación",
                    'cantidad' => $comentariosPendientes,
                    'accion' => 'Moderar comentarios',
                    'icono' => 'message-square',
                    'color' => 'blue'
                ]);
            }
        }

        // Filtrar por prioridad si se especifica
        if ($prioridad) {
            $alertas = $alertas->where('prioridad', $prioridad);
        }

        return $alertas->values()->all();
    }

    // Métodos adicionales para estadísticas específicas (implementar según necesidades)
    private function obtenerVentasPorPeriodo(array $fechas, string $agruparPor, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerTopProductosVendidos(array $fechas, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerVentasPorCanal(array $fechas, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerVentasPorMetodoPago(array $fechas, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerAnalisisConversion(array $fechas): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerComparacionPeriodoAnterior(array $fechas, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerResumenProductos(?int $categoriaId): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerProductosMasVendidos(?int $categoriaId, string $ordenarPor): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerProductosBajoStock(?int $categoriaId): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerProductosMejorCalificados(?int $categoriaId): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerAnalisisCategorias(): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerProductosMasFavoriteados(?int $categoriaId): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerTendenciasPrecios(?int $categoriaId): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerResumenUsuarios(array $fechas, ?string $rol, bool $incluirInactivos): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerNuevosRegistrosPorPeriodo(array $fechas, ?string $rol): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerUsuariosMasActivos(array $fechas, ?string $rol): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerAnalisisComportamiento(array $fechas, ?string $rol): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerDistribucionGeografica(?string $rol): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerAnalisisRetencion(array $fechas, ?string $rol): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerResumenFinanciero(array $fechas, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerFlujoCaja(array $fechas, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerAnalisisCreditos(array $fechas, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerMetodosPagoMasUtilizados(array $fechas, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerAnalisisCupones(array $fechas, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerProyeccionesFinancieras(array $fechas, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }
} 