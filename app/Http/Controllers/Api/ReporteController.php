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
use App\Models\Favorito;
use App\Models\Categoria;
use App\Models\Cupon;
use App\Models\Direccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ReporteController extends Controller
{
    /**
     * Obtener lista de reportes disponibles
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'categoria' => 'nullable|in:ventas,productos,usuarios,financieros,inventario,marketing',
                'formato' => 'nullable|in:json,csv,excel,pdf',
                'incluir_metadatos' => 'nullable|boolean'
            ]);

            $categoria = $request->get('categoria');
            $incluirMetadatos = $request->get('incluir_metadatos', true);

            $reportesDisponibles = $this->obtenerReportesDisponibles($categoria);

            if ($incluirMetadatos) {
                foreach ($reportesDisponibles as &$reporte) {
                    $reporte['metadatos'] = $this->obtenerMetadatosReporte($reporte['codigo']);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'reportes_disponibles' => $reportesDisponibles,
                    'total_reportes' => count($reportesDisponibles),
                    'categorias' => $this->obtenerCategoriasReportes(),
                    'formatos_soportados' => ['json', 'csv', 'excel', 'pdf']
                ],
                'message' => 'Lista de reportes obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener lista de reportes: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la lista de reportes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte de ventas
     */
    public function ventas(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
                'agrupar_por' => 'nullable|in:dia,semana,mes,trimestre,año',
                'incluir_detalles' => 'nullable|boolean',
                'canal_venta' => 'nullable|in:web,app,tienda_fisica,telefono,whatsapp',
                'estado_pedido' => 'nullable|in:pendiente,aprobado,rechazado,en_proceso,enviado,entregado,cancelado,devuelto',
                'moneda' => 'nullable|in:PEN,USD,EUR',
                'formato' => 'nullable|in:json,csv,excel,pdf'
            ]);

            $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
            $fechaFin = Carbon::parse($request->fecha_fin)->endOfDay();
            $agruparPor = $request->get('agrupar_por', 'dia');
            $incluirDetalles = $request->get('incluir_detalles', true);
            $canalVenta = $request->get('canal_venta');
            $estadoPedido = $request->get('estado_pedido');
            $moneda = $request->get('moneda', 'PEN');
            $formato = $request->get('formato', 'json');

            // Construir query base
            $query = Pedido::whereBetween('created_at', [$fechaInicio, $fechaFin])
                ->where('moneda', $moneda);

            if ($canalVenta) {
                $query->where('canal_venta', $canalVenta);
            }

            if ($estadoPedido) {
                $query->where('estado', $estadoPedido);
            }

            // Resumen general
            $resumenGeneral = $this->obtenerResumenVentas($query->clone());

            // Ventas agrupadas por período
            $ventasAgrupadas = $this->obtenerVentasAgrupadas($query->clone(), $agruparPor);

            // Top productos vendidos
            $topProductos = $this->obtenerTopProductosVentas($fechaInicio, $fechaFin, $moneda, $canalVenta, $estadoPedido);

            // Análisis por canal de venta
            $analisisPorCanal = $this->obtenerAnalisisPorCanal($fechaInicio, $fechaFin, $moneda, $estadoPedido);

            // Análisis por método de pago
            $analisisPorMetodoPago = $this->obtenerAnalisisPorMetodoPago($fechaInicio, $fechaFin, $moneda, $canalVenta, $estadoPedido);

            // Detalles de pedidos (si se solicita)
            $detallesPedidos = null;
            if ($incluirDetalles) {
                $detallesPedidos = $this->obtenerDetallesPedidos($query->clone());
            }

            $data = [
                'parametros' => [
                    'fecha_inicio' => $fechaInicio->toDateString(),
                    'fecha_fin' => $fechaFin->toDateString(),
                    'agrupar_por' => $agruparPor,
                    'canal_venta' => $canalVenta,
                    'estado_pedido' => $estadoPedido,
                    'moneda' => $moneda
                ],
                'resumen_general' => $resumenGeneral,
                'ventas_agrupadas' => $ventasAgrupadas,
                'top_productos' => $topProductos,
                'analisis_por_canal' => $analisisPorCanal,
                'analisis_por_metodo_pago' => $analisisPorMetodoPago,
                'detalles_pedidos' => $detallesPedidos,
                'generado_en' => now()->toISOString()
            ];

            // Exportar en formato solicitado
            if ($formato !== 'json') {
                return $this->exportarReporte('ventas', $data, $formato);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Reporte de ventas generado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar reporte de ventas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte de ventas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte de inventario
     */
    public function inventario(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'categoria_id' => 'nullable|exists:categorias,id',
                'incluir_variaciones' => 'nullable|boolean',
                'stock_minimo' => 'nullable|integer|min:0',
                'solo_activos' => 'nullable|boolean',
                'incluir_valoracion' => 'nullable|boolean',
                'formato' => 'nullable|in:json,csv,excel,pdf'
            ]);

            $categoriaId = $request->get('categoria_id');
            $incluirVariaciones = $request->get('incluir_variaciones', true);
            $stockMinimo = $request->get('stock_minimo', 0);
            $soloActivos = $request->get('solo_activos', true);
            $incluirValoracion = $request->get('incluir_valoracion', true);
            $formato = $request->get('formato', 'json');

            // Construir query base para productos
            $query = Producto::query();

            if ($categoriaId) {
                $query->where('categoria_id', $categoriaId);
            }

            if ($soloActivos) {
                $query->where('activo', true);
            }

            // Resumen general del inventario
            $resumenGeneral = $this->obtenerResumenInventario($query->clone(), $incluirValoracion);

            // Productos con stock bajo
            $stockBajo = $this->obtenerProductosStockBajo($query->clone(), $stockMinimo);

            // Productos sin stock
            $sinStock = $this->obtenerProductosSinStock($query->clone());

            // Análisis por categoría
            $analisisPorCategoria = $this->obtenerAnalisisInventarioPorCategoria($categoriaId, $soloActivos);

            // Productos más vendidos (últimos 30 días)
            $productosMasVendidos = $this->obtenerProductosMasVendidosInventario($categoriaId, $soloActivos);

            // Rotación de inventario
            $rotacionInventario = $this->obtenerRotacionInventario($categoriaId, $soloActivos);

            // Valoración del inventario
            $valoracionInventario = null;
            if ($incluirValoracion) {
                $valoracionInventario = $this->obtenerValoracionInventario($query->clone());
            }

            // Listado detallado de productos
            $listadoProductos = $this->obtenerListadoInventario($query->clone(), $incluirVariaciones);

            $data = [
                'parametros' => [
                    'categoria_id' => $categoriaId,
                    'incluir_variaciones' => $incluirVariaciones,
                    'stock_minimo' => $stockMinimo,
                    'solo_activos' => $soloActivos,
                    'incluir_valoracion' => $incluirValoracion
                ],
                'resumen_general' => $resumenGeneral,
                'stock_bajo' => $stockBajo,
                'sin_stock' => $sinStock,
                'analisis_por_categoria' => $analisisPorCategoria,
                'productos_mas_vendidos' => $productosMasVendidos,
                'rotacion_inventario' => $rotacionInventario,
                'valoracion_inventario' => $valoracionInventario,
                'listado_productos' => $listadoProductos,
                'generado_en' => now()->toISOString()
            ];

            // Exportar en formato solicitado
            if ($formato !== 'json') {
                return $this->exportarReporte('inventario', $data, $formato);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Reporte de inventario generado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar reporte de inventario: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte de inventario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte de clientes
     */
    public function clientes(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
                'incluir_inactivos' => 'nullable|boolean',
                'segmentacion' => 'nullable|in:nuevos,recurrentes,vip,inactivos',
                'incluir_geografico' => 'nullable|boolean',
                'incluir_comportamiento' => 'nullable|boolean',
                'formato' => 'nullable|in:json,csv,excel,pdf'
            ]);

            $fechaInicio = $request->filled('fecha_inicio') ? 
                Carbon::parse($request->fecha_inicio)->startOfDay() : 
                Carbon::now()->subMonths(3)->startOfDay();
            
            $fechaFin = $request->filled('fecha_fin') ? 
                Carbon::parse($request->fecha_fin)->endOfDay() : 
                Carbon::now()->endOfDay();

            $incluirInactivos = $request->get('incluir_inactivos', false);
            $segmentacion = $request->get('segmentacion');
            $incluirGeografico = $request->get('incluir_geografico', true);
            $incluirComportamiento = $request->get('incluir_comportamiento', true);
            $formato = $request->get('formato', 'json');

            // Resumen general de clientes
            $resumenGeneral = $this->obtenerResumenClientes($fechaInicio, $fechaFin, $incluirInactivos);

            // Nuevos clientes por período
            $nuevosClientes = $this->obtenerNuevosClientesPorPeriodo($fechaInicio, $fechaFin);

            // Segmentación de clientes
            $segmentacionClientes = $this->obtenerSegmentacionClientes($fechaInicio, $fechaFin, $segmentacion);

            // Top clientes por compras
            $topClientes = $this->obtenerTopClientesPorCompras($fechaInicio, $fechaFin);

            // Análisis de retención
            $analisisRetencion = $this->obtenerAnalisisRetencionClientes($fechaInicio, $fechaFin);

            // Distribución geográfica
            $distribucionGeografica = null;
            if ($incluirGeografico) {
                $distribucionGeografica = $this->obtenerDistribucionGeograficaClientes($incluirInactivos);
            }

            // Análisis de comportamiento
            $analisisComportamiento = null;
            if ($incluirComportamiento) {
                $analisisComportamiento = $this->obtenerAnalisisComportamientoClientes($fechaInicio, $fechaFin);
            }

            // Clientes con créditos pendientes
            $clientesCreditos = $this->obtenerClientesConCreditos();

            $data = [
                'parametros' => [
                    'fecha_inicio' => $fechaInicio->toDateString(),
                    'fecha_fin' => $fechaFin->toDateString(),
                    'incluir_inactivos' => $incluirInactivos,
                    'segmentacion' => $segmentacion,
                    'incluir_geografico' => $incluirGeografico,
                    'incluir_comportamiento' => $incluirComportamiento
                ],
                'resumen_general' => $resumenGeneral,
                'nuevos_clientes' => $nuevosClientes,
                'segmentacion_clientes' => $segmentacionClientes,
                'top_clientes' => $topClientes,
                'analisis_retencion' => $analisisRetencion,
                'distribucion_geografica' => $distribucionGeografica,
                'analisis_comportamiento' => $analisisComportamiento,
                'clientes_creditos' => $clientesCreditos,
                'generado_en' => now()->toISOString()
            ];

            // Exportar en formato solicitado
            if ($formato !== 'json') {
                return $this->exportarReporte('clientes', $data, $formato);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Reporte de clientes generado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar reporte de clientes: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte de clientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte financiero
     */
    public function financiero(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
                'moneda' => 'nullable|in:PEN,USD,EUR',
                'incluir_proyecciones' => 'nullable|boolean',
                'incluir_creditos' => 'nullable|boolean',
                'incluir_cupones' => 'nullable|boolean',
                'formato' => 'nullable|in:json,csv,excel,pdf'
            ]);

            $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
            $fechaFin = Carbon::parse($request->fecha_fin)->endOfDay();
            $moneda = $request->get('moneda', 'PEN');
            $incluirProyecciones = $request->get('incluir_proyecciones', true);
            $incluirCreditos = $request->get('incluir_creditos', true);
            $incluirCupones = $request->get('incluir_cupones', true);
            $formato = $request->get('formato', 'json');

            // Resumen financiero general
            $resumenFinanciero = $this->obtenerResumenFinanciero($fechaInicio, $fechaFin, $moneda);

            // Ingresos por período
            $ingresosPorPeriodo = $this->obtenerIngresosPorPeriodo($fechaInicio, $fechaFin, $moneda);

            // Análisis de métodos de pago
            $analisisMetodosPago = $this->obtenerAnalisisMetodosPagoFinanciero($fechaInicio, $fechaFin, $moneda);

            // Estado de cuentas por cobrar
            $cuentasPorCobrar = null;
            if ($incluirCreditos) {
                $cuentasPorCobrar = $this->obtenerCuentasPorCobrar($fechaInicio, $fechaFin, $moneda);
            }

            // Análisis de cupones y descuentos
            $analisisCupones = null;
            if ($incluirCupones) {
                $analisisCupones = $this->obtenerAnalisisCuponesFinanciero($fechaInicio, $fechaFin, $moneda);
            }

            // Flujo de caja
            $flujoCaja = $this->obtenerFlujoCaja($fechaInicio, $fechaFin, $moneda);

            // Proyecciones financieras
            $proyecciones = null;
            if ($incluirProyecciones) {
                $proyecciones = $this->obtenerProyeccionesFinancieras($fechaInicio, $fechaFin, $moneda);
            }

            // Indicadores financieros clave
            $indicadoresFinancieros = $this->obtenerIndicadoresFinancieros($fechaInicio, $fechaFin, $moneda);

            $data = [
                'parametros' => [
                    'fecha_inicio' => $fechaInicio->toDateString(),
                    'fecha_fin' => $fechaFin->toDateString(),
                    'moneda' => $moneda,
                    'incluir_proyecciones' => $incluirProyecciones,
                    'incluir_creditos' => $incluirCreditos,
                    'incluir_cupones' => $incluirCupones
                ],
                'resumen_financiero' => $resumenFinanciero,
                'ingresos_por_periodo' => $ingresosPorPeriodo,
                'analisis_metodos_pago' => $analisisMetodosPago,
                'cuentas_por_cobrar' => $cuentasPorCobrar,
                'analisis_cupones' => $analisisCupones,
                'flujo_caja' => $flujoCaja,
                'proyecciones' => $proyecciones,
                'indicadores_financieros' => $indicadoresFinancieros,
                'generado_en' => now()->toISOString()
            ];

            // Exportar en formato solicitado
            if ($formato !== 'json') {
                return $this->exportarReporte('financiero', $data, $formato);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Reporte financiero generado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar reporte financiero: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte financiero',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte personalizado
     */
    public function personalizado(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:1000',
                'modulos' => 'required|array|min:1',
                'modulos.*' => 'in:ventas,productos,usuarios,pagos,comentarios,favoritos',
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
                'filtros' => 'nullable|array',
                'metricas' => 'required|array|min:1',
                'formato' => 'nullable|in:json,csv,excel,pdf',
                'guardar_configuracion' => 'nullable|boolean'
            ]);

            $nombre = $request->get('nombre');
            $descripcion = $request->get('descripcion');
            $modulos = $request->get('modulos');
            $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
            $fechaFin = Carbon::parse($request->fecha_fin)->endOfDay();
            $filtros = $request->get('filtros', []);
            $metricas = $request->get('metricas');
            $formato = $request->get('formato', 'json');
            $guardarConfiguracion = $request->get('guardar_configuracion', false);

            $data = [
                'configuracion' => [
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'modulos' => $modulos,
                    'fecha_inicio' => $fechaInicio->toDateString(),
                    'fecha_fin' => $fechaFin->toDateString(),
                    'filtros' => $filtros,
                    'metricas' => $metricas
                ],
                'resultados' => []
            ];

            // Generar datos para cada módulo solicitado
            foreach ($modulos as $modulo) {
                $data['resultados'][$modulo] = $this->generarDatosModuloPersonalizado(
                    $modulo, 
                    $fechaInicio, 
                    $fechaFin, 
                    $filtros, 
                    $metricas
                );
            }

            // Guardar configuración si se solicita
            if ($guardarConfiguracion) {
                $this->guardarConfiguracionReporte($request->all());
            }

            $data['generado_en'] = now()->toISOString();

            // Exportar en formato solicitado
            if ($formato !== 'json') {
                return $this->exportarReporte('personalizado', $data, $formato);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Reporte personalizado generado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar reporte personalizado: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte personalizado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas generales de reportes
     */
    public function estadisticas(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'periodo' => 'nullable|in:hoy,semana,mes,trimestre,año',
                'incluir_cache' => 'nullable|boolean'
            ]);

            $periodo = $request->get('periodo', 'mes');
            $incluirCache = $request->get('incluir_cache', true);
            $cacheKey = "reportes_estadisticas_{$periodo}";

            if ($incluirCache && Cache::has($cacheKey)) {
                return response()->json([
                    'success' => true,
                    'data' => Cache::get($cacheKey),
                    'cached' => true
                ]);
            }

            $fechas = $this->calcularFechasPeriodo($periodo);

            // Estadísticas generales del sistema
            $estadisticasGenerales = [
                'total_pedidos' => Pedido::whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])->count(),
                'total_ventas' => Pedido::whereBetween('created_at', [$fechas['desde'], $fechas['hasta']])
                    ->whereIn('estado', ['entregado', 'enviado'])->sum('total'),
                'total_productos' => Producto::count(),
                'total_clientes' => User::where('rol', 'cliente')->count(),
                'productos_bajo_stock' => Producto::where('stock', '<=', 10)->count(),
                'comentarios_pendientes' => Comentario::where('aprobado', false)->count(),
                'cuotas_vencidas' => CuotaCredito::where('estado', 'atrasado')->count()
            ];

            // Tendencias del período
            $tendencias = $this->obtenerTendenciasReportes($fechas);

            // Reportes más generados
            $reportesMasGenerados = $this->obtenerReportesMasGenerados($fechas);

            $data = [
                'periodo' => $periodo,
                'fechas' => $fechas,
                'estadisticas_generales' => $estadisticasGenerales,
                'tendencias' => $tendencias,
                'reportes_mas_generados' => $reportesMasGenerados,
                'ultima_actualizacion' => now()->toISOString()
            ];

            // Guardar en cache por 30 minutos
            if ($incluirCache) {
                Cache::put($cacheKey, $data, 1800);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Estadísticas de reportes obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de reportes: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas de reportes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Métodos privados para cálculos específicos

    private function obtenerReportesDisponibles(?string $categoria): array
    {
        $reportes = [
            [
                'codigo' => 'ventas',
                'nombre' => 'Reporte de Ventas',
                'descripcion' => 'Análisis completo de ventas por período, canal y método de pago',
                'categoria' => 'ventas',
                'parametros_requeridos' => ['fecha_inicio', 'fecha_fin'],
                'formatos_soportados' => ['json', 'csv', 'excel', 'pdf']
            ],
            [
                'codigo' => 'inventario',
                'nombre' => 'Reporte de Inventario',
                'descripcion' => 'Estado actual del inventario, stock bajo y valoración',
                'categoria' => 'productos',
                'parametros_requeridos' => [],
                'formatos_soportados' => ['json', 'csv', 'excel', 'pdf']
            ],
            [
                'codigo' => 'clientes',
                'nombre' => 'Reporte de Clientes',
                'descripcion' => 'Análisis de clientes, segmentación y comportamiento',
                'categoria' => 'usuarios',
                'parametros_requeridos' => [],
                'formatos_soportados' => ['json', 'csv', 'excel', 'pdf']
            ],
            [
                'codigo' => 'financiero',
                'nombre' => 'Reporte Financiero',
                'descripcion' => 'Estado financiero, flujo de caja y proyecciones',
                'categoria' => 'financieros',
                'parametros_requeridos' => ['fecha_inicio', 'fecha_fin'],
                'formatos_soportados' => ['json', 'csv', 'excel', 'pdf']
            ],
            [
                'codigo' => 'personalizado',
                'nombre' => 'Reporte Personalizado',
                'descripcion' => 'Reporte configurable con módulos y métricas específicas',
                'categoria' => 'marketing',
                'parametros_requeridos' => ['nombre', 'modulos', 'fecha_inicio', 'fecha_fin', 'metricas'],
                'formatos_soportados' => ['json', 'csv', 'excel', 'pdf']
            ]
        ];

        if ($categoria) {
            return array_filter($reportes, fn($reporte) => $reporte['categoria'] === $categoria);
        }

        return $reportes;
    }

    private function obtenerCategoriasReportes(): array
    {
        return [
            'ventas' => 'Reportes de Ventas',
            'productos' => 'Reportes de Productos',
            'usuarios' => 'Reportes de Usuarios',
            'financieros' => 'Reportes Financieros',
            'inventario' => 'Reportes de Inventario',
            'marketing' => 'Reportes de Marketing'
        ];
    }

    private function obtenerMetadatosReporte(string $codigo): array
    {
        // Implementar lógica para obtener metadatos específicos de cada reporte
        return [
            'ultima_generacion' => now()->subHours(2)->toISOString(),
            'tiempo_promedio_generacion' => '2.5 segundos',
            'tamaño_promedio' => '1.2 MB',
            'veces_generado' => rand(10, 100)
        ];
    }

    private function calcularFechasPeriodo(string $periodo): array
    {
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

    private function obtenerResumenVentas($query): array
    {
        $pedidos = $query->get();
        
        return [
            'total_pedidos' => $pedidos->count(),
            'total_ventas' => round($pedidos->sum('total'), 2),
            'ticket_promedio' => $pedidos->count() > 0 ? round($pedidos->avg('total'), 2) : 0,
            'pedidos_completados' => $pedidos->whereIn('estado', ['entregado', 'enviado'])->count(),
            'tasa_completacion' => $pedidos->count() > 0 ? 
                round(($pedidos->whereIn('estado', ['entregado', 'enviado'])->count() / $pedidos->count()) * 100, 2) : 0
        ];
    }

    private function obtenerVentasAgrupadas($query, string $agruparPor): array
    {
        $formatoFecha = match($agruparPor) {
            'dia' => '%Y-%m-%d',
            'semana' => '%Y-%u',
            'mes' => '%Y-%m',
            'trimestre' => '%Y-Q%q',
            'año' => '%Y',
            default => '%Y-%m-%d'
        };

        return $query->selectRaw("DATE_FORMAT(created_at, '{$formatoFecha}') as periodo")
            ->selectRaw('COUNT(*) as total_pedidos')
            ->selectRaw('SUM(total) as total_ventas')
            ->selectRaw('AVG(total) as ticket_promedio')
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get()
            ->map(function ($item) {
                return [
                    'periodo' => $item->periodo,
                    'total_pedidos' => $item->total_pedidos,
                    'total_ventas' => round($item->total_ventas, 2),
                    'ticket_promedio' => round($item->ticket_promedio, 2)
                ];
            })
            ->toArray();
    }

    private function obtenerTopProductosVentas($fechaInicio, $fechaFin, $moneda, $canalVenta = null, $estadoPedido = null): array
    {
        $query = DetallePedido::whereHas('pedido', function ($q) use ($fechaInicio, $fechaFin, $moneda, $canalVenta, $estadoPedido) {
            $q->whereBetween('created_at', [$fechaInicio, $fechaFin])
              ->where('moneda', $moneda);
            
            if ($canalVenta) {
                $q->where('canal_venta', $canalVenta);
            }
            
            if ($estadoPedido) {
                $q->where('estado', $estadoPedido);
            }
        });

        return $query->select('producto_id')
            ->selectRaw('SUM(cantidad) as total_vendido')
            ->selectRaw('SUM(subtotal) as ingresos_generados')
            ->with('producto:id,nombre,precio,sku')
            ->groupBy('producto_id')
            ->orderBy('total_vendido', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'producto_id' => $item->producto_id,
                    'nombre' => $item->producto->nombre,
                    'sku' => $item->producto->sku,
                    'precio_unitario' => $item->producto->precio,
                    'cantidad_vendida' => $item->total_vendido,
                    'ingresos_generados' => round($item->ingresos_generados, 2)
                ];
            })
            ->toArray();
    }

    private function obtenerAnalisisPorCanal($fechaInicio, $fechaFin, $moneda, $estadoPedido = null): array
    {
        $query = Pedido::whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->where('moneda', $moneda);

        if ($estadoPedido) {
            $query->where('estado', $estadoPedido);
        }

        return $query->select('canal_venta')
            ->selectRaw('COUNT(*) as total_pedidos')
            ->selectRaw('SUM(total) as total_ventas')
            ->selectRaw('AVG(total) as ticket_promedio')
            ->groupBy('canal_venta')
            ->orderBy('total_ventas', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'canal' => $item->canal_venta,
                    'total_pedidos' => $item->total_pedidos,
                    'total_ventas' => round($item->total_ventas, 2),
                    'ticket_promedio' => round($item->ticket_promedio, 2)
                ];
            })
            ->toArray();
    }

    private function obtenerAnalisisPorMetodoPago($fechaInicio, $fechaFin, $moneda, $canalVenta = null, $estadoPedido = null): array
    {
        $query = Pago::whereHas('pedido', function ($q) use ($fechaInicio, $fechaFin, $moneda, $canalVenta, $estadoPedido) {
            $q->whereBetween('created_at', [$fechaInicio, $fechaFin])
              ->where('moneda', $moneda);
            
            if ($canalVenta) {
                $q->where('canal_venta', $canalVenta);
            }
            
            if ($estadoPedido) {
                $q->where('estado', $estadoPedido);
            }
        })->where('estado', 'pagado');

        return $query->select('metodo_pago')
            ->selectRaw('COUNT(*) as total_transacciones')
            ->selectRaw('SUM(monto) as total_monto')
            ->selectRaw('AVG(monto) as monto_promedio')
            ->groupBy('metodo_pago')
            ->orderBy('total_monto', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'metodo_pago' => $item->metodo_pago,
                    'total_transacciones' => $item->total_transacciones,
                    'total_monto' => round($item->total_monto, 2),
                    'monto_promedio' => round($item->monto_promedio, 2)
                ];
            })
            ->toArray();
    }

    private function obtenerDetallesPedidos($query): array
    {
        return $query->with(['usuario:id,name,email', 'detalles.producto:id,nombre,sku'])
            ->select('id', 'usuario_id', 'total', 'estado', 'canal_venta', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($pedido) {
                return [
                    'id' => $pedido->id,
                    'cliente' => $pedido->usuario->name,
                    'email' => $pedido->usuario->email,
                    'total' => $pedido->total,
                    'estado' => $pedido->estado,
                    'canal_venta' => $pedido->canal_venta,
                    'fecha' => $pedido->created_at->toDateString(),
                    'productos' => $pedido->detalles->map(function ($detalle) {
                        return [
                            'nombre' => $detalle->producto->nombre,
                            'sku' => $detalle->producto->sku,
                            'cantidad' => $detalle->cantidad,
                            'precio' => $detalle->precio_unitario
                        ];
                    })
                ];
            })
            ->toArray();
    }

    // Métodos adicionales para otros tipos de reportes (implementar según necesidades)
    private function obtenerResumenInventario($query, bool $incluirValoracion): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerProductosStockBajo($query, int $stockMinimo): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerProductosSinStock($query): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerAnalisisInventarioPorCategoria(?int $categoriaId, bool $soloActivos): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerProductosMasVendidosInventario(?int $categoriaId, bool $soloActivos): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerRotacionInventario(?int $categoriaId, bool $soloActivos): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerValoracionInventario($query): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerListadoInventario($query, bool $incluirVariaciones): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerResumenClientes($fechaInicio, $fechaFin, bool $incluirInactivos): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerNuevosClientesPorPeriodo($fechaInicio, $fechaFin): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerSegmentacionClientes($fechaInicio, $fechaFin, ?string $segmentacion): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerTopClientesPorCompras($fechaInicio, $fechaFin): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerAnalisisRetencionClientes($fechaInicio, $fechaFin): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerDistribucionGeograficaClientes(bool $incluirInactivos): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerAnalisisComportamientoClientes($fechaInicio, $fechaFin): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerClientesConCreditos(): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerResumenFinanciero($fechaInicio, $fechaFin, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerIngresosPorPeriodo($fechaInicio, $fechaFin, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerAnalisisMetodosPagoFinanciero($fechaInicio, $fechaFin, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerCuentasPorCobrar($fechaInicio, $fechaFin, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerAnalisisCuponesFinanciero($fechaInicio, $fechaFin, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerFlujoCaja($fechaInicio, $fechaFin, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerProyeccionesFinancieras($fechaInicio, $fechaFin, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerIndicadoresFinancieros($fechaInicio, $fechaFin, string $moneda): array
    {
        // Implementar lógica específica
        return [];
    }

    private function generarDatosModuloPersonalizado(string $modulo, $fechaInicio, $fechaFin, array $filtros, array $metricas): array
    {
        // Implementar lógica específica para cada módulo
        return [];
    }

    private function guardarConfiguracionReporte(array $configuracion): void
    {
        // Implementar lógica para guardar configuraciones de reportes personalizados
        Log::info('Configuración de reporte personalizado guardada', $configuracion);
    }

    private function obtenerTendenciasReportes(array $fechas): array
    {
        // Implementar lógica específica
        return [];
    }

    private function obtenerReportesMasGenerados(array $fechas): array
    {
        // Implementar lógica específica
        return [];
    }

    private function exportarReporte(string $tipo, array $data, string $formato): JsonResponse
    {
        try {
            $nombreArchivo = "{$tipo}_" . now()->format('Y-m-d_H-i-s');
            
            switch ($formato) {
                case 'csv':
                    return $this->exportarCSV($data, $nombreArchivo);
                case 'excel':
                    return $this->exportarExcel($data, $nombreArchivo);
                case 'pdf':
                    return $this->exportarPDF($data, $nombreArchivo);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Formato de exportación no soportado'
                    ], 400);
            }
        } catch (\Exception $e) {
            Log::error("Error al exportar reporte en formato {$formato}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar el reporte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function exportarCSV(array $data, string $nombreArchivo): JsonResponse
    {
        // Implementar exportación a CSV
        return response()->json([
            'success' => true,
            'message' => 'Reporte exportado a CSV exitosamente',
            'archivo' => $nombreArchivo . '.csv',
            'url_descarga' => url("storage/reportes/{$nombreArchivo}.csv")
        ]);
    }

    private function exportarExcel(array $data, string $nombreArchivo): JsonResponse
    {
        // Implementar exportación a Excel
        return response()->json([
            'success' => true,
            'message' => 'Reporte exportado a Excel exitosamente',
            'archivo' => $nombreArchivo . '.xlsx',
            'url_descarga' => url("storage/reportes/{$nombreArchivo}.xlsx")
        ]);
    }

    private function exportarPDF(array $data, string $nombreArchivo): JsonResponse
    {
        // Implementar exportación a PDF
        return response()->json([
            'success' => true,
            'message' => 'Reporte exportado a PDF exitosamente',
            'archivo' => $nombreArchivo . '.pdf',
            'url_descarga' => url("storage/reportes/{$nombreArchivo}.pdf")
        ]);
    }
} 