<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventarioMovimientoResource;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\VariacionProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InventarioMovimientoController extends Controller
{
    /**
     * Obtener lista de movimientos de inventario con filtros
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'producto_id' => 'nullable|exists:productos,id',
                'variacion_id' => 'nullable|exists:variaciones_productos,id',
                'tipo_movimiento' => 'nullable|in:entrada,salida,ajuste,reserva,liberacion',
                'usuario_id' => 'nullable|exists:users,id',
                'fecha_desde' => 'nullable|date',
                'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'order_by' => 'nullable|in:created_at,tipo_movimiento,cantidad,stock_nuevo',
                'order_direction' => 'nullable|in:asc,desc',
            ]);

            $query = InventarioMovimiento::query()
                ->with(['producto', 'variacion', 'usuario']);

            // Aplicar filtros
            if (!empty($validated['producto_id'])) {
                $query->where('producto_id', $validated['producto_id']);
            }

            if (!empty($validated['variacion_id'])) {
                $query->where('variacion_id', $validated['variacion_id']);
            }

            if (!empty($validated['tipo_movimiento'])) {
                $query->where('tipo_movimiento', $validated['tipo_movimiento']);
            }

            if (!empty($validated['usuario_id'])) {
                $query->where('usuario_id', $validated['usuario_id']);
            }

            if (!empty($validated['fecha_desde'])) {
                $query->where('created_at', '>=', $validated['fecha_desde']);
            }

            if (!empty($validated['fecha_hasta'])) {
                $query->where('created_at', '<=', $validated['fecha_hasta'] . ' 23:59:59');
            }

            // Ordenamiento
            $orderBy = $validated['order_by'] ?? 'created_at';
            $orderDirection = $validated['order_direction'] ?? 'desc';
            $query->orderBy($orderBy, $orderDirection);

            // Paginación
            $perPage = $validated['per_page'] ?? 15;
            $movimientos = $query->paginate($perPage);

            return response()->json([
                'data' => InventarioMovimientoResource::collection($movimientos),
                'meta' => [
                    'total' => $movimientos->total(),
                    'per_page' => $movimientos->perPage(),
                    'current_page' => $movimientos->currentPage(),
                    'last_page' => $movimientos->lastPage(),
                    'from' => $movimientos->firstItem(),
                    'to' => $movimientos->lastItem(),
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al obtener movimientos de inventario: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener movimientos de inventario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar nuevo movimiento de inventario
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'producto_id' => 'required|exists:productos,id',
                'variacion_id' => 'nullable|exists:variaciones_productos,id',
                'tipo_movimiento' => 'required|in:entrada,salida,ajuste,reserva,liberacion',
                'cantidad' => 'required|numeric|min:0.01',
                'motivo' => 'required|string|max:500',
                'referencia' => 'nullable|string|max:100',
            ]);

            DB::beginTransaction();

            // Verificar que el producto existe y está activo
            $producto = Producto::findOrFail($validated['producto_id']);
            if (!$producto->activo) {
                throw ValidationException::withMessages([
                    'producto_id' => ['El producto no está activo']
                ]);
            }

            // Verificar variación si se proporciona
            $variacion = null;
            if (!empty($validated['variacion_id'])) {
                $variacion = VariacionProducto::findOrFail($validated['variacion_id']);
                
                if ($variacion->producto_id !== $producto->id) {
                    throw ValidationException::withMessages([
                        'variacion_id' => ['La variación no pertenece al producto especificado']
                    ]);
                }

                if (!$variacion->disponible) {
                    throw ValidationException::withMessages([
                        'variacion_id' => ['La variación no está disponible']
                    ]);
                }
            }

            // Obtener stock actual
            $stockActual = $this->obtenerStockActual($producto, $variacion);

            // Calcular nuevo stock
            $nuevoStock = $this->calcularNuevoStock(
                $stockActual, 
                $validated['cantidad'], 
                $validated['tipo_movimiento']
            );

            // Validar que el stock no quede negativo (excepto para ajustes)
            if ($nuevoStock < 0 && $validated['tipo_movimiento'] !== 'ajuste') {
                throw ValidationException::withMessages([
                    'cantidad' => ['Stock insuficiente. Stock actual: ' . $stockActual]
                ]);
            }

            // Crear el movimiento
            $movimiento = InventarioMovimiento::create([
                'producto_id' => $validated['producto_id'],
                'variacion_id' => $validated['variacion_id'] ?? null,
                'tipo_movimiento' => $validated['tipo_movimiento'],
                'cantidad' => $this->calcularCantidadMovimiento(
                    $stockActual, 
                    $nuevoStock, 
                    $validated['cantidad'], 
                    $validated['tipo_movimiento']
                ),
                'stock_anterior' => $stockActual,
                'stock_nuevo' => $nuevoStock,
                'motivo' => $validated['motivo'],
                'referencia' => $validated['referencia'] ?? null,
                'usuario_id' => auth()->id(),
            ]);

            // Actualizar stock en el producto o variación
            $this->actualizarStock($producto, $variacion, $nuevoStock);

            $movimiento->load(['producto', 'variacion', 'usuario']);

            DB::commit();

            return response()->json([
                'message' => 'Movimiento de inventario registrado exitosamente',
                'data' => new InventarioMovimientoResource($movimiento)
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al registrar movimiento de inventario: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al registrar movimiento de inventario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar movimiento específico
     */
    public function show(InventarioMovimiento $inventarioMovimiento): JsonResponse
    {
        try {
            $inventarioMovimiento->load(['producto', 'variacion', 'usuario']);
            
            return response()->json([
                'data' => new InventarioMovimientoResource($inventarioMovimiento)
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener movimiento de inventario: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener el movimiento de inventario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener reporte de movimientos de inventario
     */
    public function obtenerReporte(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fecha_desde' => 'required|date',
                'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
                'producto_id' => 'nullable|exists:productos,id',
                'variacion_id' => 'nullable|exists:variaciones_productos,id',
                'tipo_movimiento' => 'nullable|in:entrada,salida,ajuste,reserva,liberacion',
                'usuario_id' => 'nullable|exists:users,id',
                'incluir_detalles' => 'nullable|boolean',
            ]);

            $query = InventarioMovimiento::query()
                ->with(['producto', 'variacion', 'usuario'])
                ->whereBetween('created_at', [
                    $validated['fecha_desde'] . ' 00:00:00',
                    $validated['fecha_hasta'] . ' 23:59:59'
                ]);

            // Aplicar filtros adicionales
            if (!empty($validated['producto_id'])) {
                $query->where('producto_id', $validated['producto_id']);
            }

            if (!empty($validated['variacion_id'])) {
                $query->where('variacion_id', $validated['variacion_id']);
            }

            if (!empty($validated['tipo_movimiento'])) {
                $query->where('tipo_movimiento', $validated['tipo_movimiento']);
            }

            if (!empty($validated['usuario_id'])) {
                $query->where('usuario_id', $validated['usuario_id']);
            }

            $movimientos = $query->orderBy('created_at', 'desc')->get();

            // Calcular resumen
            $resumen = $this->calcularResumenMovimientos($movimientos);

            $response = [
                'periodo' => [
                    'fecha_desde' => $validated['fecha_desde'],
                    'fecha_hasta' => $validated['fecha_hasta'],
                ],
                'resumen' => $resumen,
            ];

            // Incluir detalles si se solicita
            if ($validated['incluir_detalles'] ?? false) {
                $response['movimientos'] = InventarioMovimientoResource::collection($movimientos);
            }

            return response()->json($response);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al generar reporte de inventario: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al generar reporte de inventario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de movimientos
     */
    public function estadisticas(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'dias' => 'nullable|integer|min:1|max:365',
                'producto_id' => 'nullable|exists:productos,id',
            ]);

            $dias = $validated['dias'] ?? 30;
            $fechaDesde = now()->subDays($dias)->startOfDay();

            $query = InventarioMovimiento::query()
                ->where('created_at', '>=', $fechaDesde);

            if (!empty($validated['producto_id'])) {
                $query->where('producto_id', $validated['producto_id']);
            }

            $movimientos = $query->get();

            $estadisticas = [
                'periodo_dias' => $dias,
                'total_movimientos' => $movimientos->count(),
                'por_tipo' => [
                    'entradas' => $movimientos->where('tipo_movimiento', 'entrada')->count(),
                    'salidas' => $movimientos->where('tipo_movimiento', 'salida')->count(),
                    'ajustes' => $movimientos->where('tipo_movimiento', 'ajuste')->count(),
                    'reservas' => $movimientos->where('tipo_movimiento', 'reserva')->count(),
                    'liberaciones' => $movimientos->where('tipo_movimiento', 'liberacion')->count(),
                ],
                'cantidades' => [
                    'total_entradas' => $movimientos->where('tipo_movimiento', 'entrada')->sum('cantidad'),
                    'total_salidas' => abs($movimientos->where('tipo_movimiento', 'salida')->sum('cantidad')),
                    'total_ajustes' => $movimientos->where('tipo_movimiento', 'ajuste')->sum('cantidad'),
                ],
                'productos_mas_movidos' => $this->obtenerProductosMasMovidos($movimientos),
                'usuarios_mas_activos' => $this->obtenerUsuariosMasActivos($movimientos),
            ];

            return response()->json($estadisticas);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de inventario: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener estadísticas de inventario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener stock actual de un producto o variación
     */
    private function obtenerStockActual(Producto $producto, ?VariacionProducto $variacion = null): float
    {
        if ($variacion) {
            return (float) $variacion->stock ?? 0;
        }
        
        return (float) $producto->stock ?? 0;
    }

    /**
     * Calcular nuevo stock basado en el tipo de movimiento
     */
    private function calcularNuevoStock(float $stockActual, float $cantidad, string $tipoMovimiento): float
    {
        return match($tipoMovimiento) {
            'entrada', 'liberacion' => $stockActual + $cantidad,
            'salida', 'reserva' => $stockActual - $cantidad,
            'ajuste' => $cantidad, // En ajustes, la cantidad es el stock final deseado
            default => $stockActual
        };
    }

    /**
     * Calcular la cantidad del movimiento para registrar
     */
    private function calcularCantidadMovimiento(
        float $stockActual, 
        float $nuevoStock, 
        float $cantidadInput, 
        string $tipoMovimiento
    ): float {
        if ($tipoMovimiento === 'ajuste') {
            // Para ajustes, la cantidad es la diferencia
            return $nuevoStock - $stockActual;
        }
        
        // Para otros tipos, usar la cantidad proporcionada con el signo correcto
        return match($tipoMovimiento) {
            'salida', 'reserva' => -abs($cantidadInput),
            default => abs($cantidadInput)
        };
    }

    /**
     * Actualizar stock en producto o variación
     */
    private function actualizarStock(Producto $producto, ?VariacionProducto $variacion, float $nuevoStock): void
    {
        if ($variacion) {
            $variacion->update(['stock' => $nuevoStock]);
        } else {
            $producto->update(['stock' => $nuevoStock]);
        }
    }

    /**
     * Calcular resumen de movimientos
     */
    private function calcularResumenMovimientos($movimientos): array
    {
        return [
            'total_movimientos' => $movimientos->count(),
            'entradas' => [
                'cantidad' => $movimientos->where('tipo_movimiento', 'entrada')->count(),
                'total_unidades' => $movimientos->where('tipo_movimiento', 'entrada')->sum('cantidad'),
            ],
            'salidas' => [
                'cantidad' => $movimientos->where('tipo_movimiento', 'salida')->count(),
                'total_unidades' => abs($movimientos->where('tipo_movimiento', 'salida')->sum('cantidad')),
            ],
            'ajustes' => [
                'cantidad' => $movimientos->where('tipo_movimiento', 'ajuste')->count(),
                'total_unidades' => $movimientos->where('tipo_movimiento', 'ajuste')->sum('cantidad'),
            ],
            'reservas' => [
                'cantidad' => $movimientos->where('tipo_movimiento', 'reserva')->count(),
                'total_unidades' => abs($movimientos->where('tipo_movimiento', 'reserva')->sum('cantidad')),
            ],
            'liberaciones' => [
                'cantidad' => $movimientos->where('tipo_movimiento', 'liberacion')->count(),
                'total_unidades' => $movimientos->where('tipo_movimiento', 'liberacion')->sum('cantidad'),
            ],
        ];
    }

    /**
     * Obtener productos más movidos
     */
    private function obtenerProductosMasMovidos($movimientos): array
    {
        return $movimientos->groupBy('producto_id')
            ->map(function ($grupo) {
                $producto = $grupo->first()->producto;
                return [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'sku' => $producto->sku,
                    'total_movimientos' => $grupo->count(),
                    'total_cantidad' => $grupo->sum(fn($m) => abs($m->cantidad)),
                ];
            })
            ->sortByDesc('total_movimientos')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Obtener usuarios más activos
     */
    private function obtenerUsuariosMasActivos($movimientos): array
    {
        return $movimientos->groupBy('usuario_id')
            ->map(function ($grupo) {
                $usuario = $grupo->first()->usuario;
                return [
                    'usuario_id' => $usuario->id,
                    'nombre' => $usuario->nombre ?? $usuario->name,
                    'email' => $usuario->email,
                    'total_movimientos' => $grupo->count(),
                ];
            })
            ->sortByDesc('total_movimientos')
            ->take(10)
            ->values()
            ->toArray();
    }
} 