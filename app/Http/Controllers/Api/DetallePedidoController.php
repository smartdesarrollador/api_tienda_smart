<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDetallePedidoRequest;
use App\Http\Requests\UpdateDetallePedidoRequest;
use App\Http\Resources\DetallePedidoResource;
use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\VariacionProducto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DetallePedidoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = DetallePedido::with(['pedido.usuario', 'producto', 'variacion']);

            // Filtro por pedido específico
            if ($request->filled('pedido_id')) {
                $query->where('pedido_id', $request->pedido_id);
            }

            // Filtro por producto
            if ($request->filled('producto_id')) {
                $query->where('producto_id', $request->producto_id);
            }

            // Filtro por variación
            if ($request->filled('variacion_id')) {
                $query->where('variacion_id', $request->variacion_id);
            }

            // Filtro por cantidad mínima
            if ($request->filled('cantidad_min')) {
                $query->where('cantidad', '>=', $request->cantidad_min);
            }

            // Filtro por cantidad máxima
            if ($request->filled('cantidad_max')) {
                $query->where('cantidad', '<=', $request->cantidad_max);
            }

            // Filtro por rango de precios
            if ($request->filled('precio_min')) {
                $query->where('precio_unitario', '>=', $request->precio_min);
            }

            if ($request->filled('precio_max')) {
                $query->where('precio_unitario', '<=', $request->precio_max);
            }

            // Filtro por rango de subtotales
            if ($request->filled('subtotal_min')) {
                $query->where('subtotal', '>=', $request->subtotal_min);
            }

            if ($request->filled('subtotal_max')) {
                $query->where('subtotal', '<=', $request->subtotal_max);
            }

            // Filtro por moneda
            if ($request->filled('moneda')) {
                $query->where('moneda', $request->moneda);
            }

            // Filtro por rango de fechas
            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            // Búsqueda en productos relacionados
            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('producto', function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('codigo_barras', 'like', "%{$search}%");
                });
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $allowedSorts = ['created_at', 'updated_at', 'cantidad', 'precio_unitario', 'subtotal'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Paginación
            $perPage = min($request->get('per_page', 15), 100);
            $detalles = $query->paginate($perPage);

            return response()->json([
                'data' => DetallePedidoResource::collection($detalles->items()),
                'meta' => [
                    'current_page' => $detalles->currentPage(),
                    'last_page' => $detalles->lastPage(),
                    'per_page' => $detalles->perPage(),
                    'total' => $detalles->total(),
                    'from' => $detalles->firstItem(),
                    'to' => $detalles->lastItem(),
                ],
                'links' => [
                    'first' => $detalles->url(1),
                    'last' => $detalles->url($detalles->lastPage()),
                    'prev' => $detalles->previousPageUrl(),
                    'next' => $detalles->nextPageUrl(),
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener detalles de pedidos: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los detalles de pedidos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDetallePedidoRequest $request): JsonResponse
    {
        try {
            $datosValidados = $request->validated();

            DB::beginTransaction();

            // Verificar que el pedido existe y está en estado editable
            $pedido = Pedido::findOrFail($datosValidados['pedido_id']);
            
            if (!in_array($pedido->estado, ['pendiente', 'aprobado'])) {
                return response()->json([
                    'message' => 'No se pueden agregar items a un pedido en estado: ' . $pedido->estado,
                    'estado_actual' => $pedido->estado
                ], 422);
            }

            // Obtener producto y variación si aplica
            $producto = Producto::findOrFail($datosValidados['producto_id']);
            $variacion = null;

            if (isset($datosValidados['variacion_id'])) {
                $variacion = VariacionProducto::where('id', $datosValidados['variacion_id'])
                    ->where('producto_id', $producto->id)
                    ->firstOrFail();
            }

            // Verificar stock disponible
            $stockDisponible = $variacion ? $variacion->stock : $producto->stock;
            if ($stockDisponible < $datosValidados['cantidad']) {
                return response()->json([
                    'message' => 'Stock insuficiente.',
                    'stock_disponible' => $stockDisponible,
                    'cantidad_solicitada' => $datosValidados['cantidad']
                ], 422);
            }

            // Calcular precios
            $precioUnitario = $variacion 
                ? ($variacion->precio_oferta ?? $variacion->precio)
                : ($producto->precio_oferta ?? $producto->precio);

            $subtotal = $precioUnitario * $datosValidados['cantidad'];
            $descuento = $datosValidados['descuento'] ?? 0;
            $impuesto = ($subtotal - $descuento) * 0.18; // IGV 18%

            // Crear el detalle del pedido
            $detalle = DetallePedido::create([
                'pedido_id' => $datosValidados['pedido_id'],
                'producto_id' => $datosValidados['producto_id'],
                'variacion_id' => $datosValidados['variacion_id'] ?? null,
                'cantidad' => $datosValidados['cantidad'],
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuesto' => $impuesto,
                'moneda' => $datosValidados['moneda'] ?? 'PEN',
            ]);

            // Reducir stock
            if ($variacion) {
                $variacion->decrement('stock', $datosValidados['cantidad']);
            } else {
                $producto->decrement('stock', $datosValidados['cantidad']);
            }

            // Recalcular total del pedido
            $this->recalcularTotalPedido($pedido);

            DB::commit();

            Log::info("Detalle de pedido creado exitosamente", [
                'detalle_id' => $detalle->id,
                'pedido_id' => $pedido->id,
                'producto_id' => $producto->id,
                'variacion_id' => $variacion?->id,
                'cantidad' => $datosValidados['cantidad']
            ]);

            return response()->json([
                'message' => 'Detalle de pedido creado exitosamente.',
                'data' => new DetallePedidoResource($detalle->load(['pedido.usuario', 'producto', 'variacion']))
            ], 201);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Recurso no encontrado.',
                'error' => $e->getMessage()
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al crear detalle de pedido: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al crear el detalle de pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(DetallePedido $detallePedido): JsonResponse
    {
        try {
            $detallePedido->load(['pedido.usuario', 'producto', 'variacion']);

            return response()->json([
                'data' => new DetallePedidoResource($detallePedido)
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener detalle de pedido ID {$detallePedido->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener el detalle de pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDetallePedidoRequest $request, DetallePedido $detallePedido): JsonResponse
    {
        try {
            $datosValidados = $request->validated();

            DB::beginTransaction();

            // Verificar que el pedido está en estado editable
            $pedido = $detallePedido->pedido;
            
            if (!in_array($pedido->estado, ['pendiente', 'aprobado'])) {
                return response()->json([
                    'message' => 'No se pueden modificar items de un pedido en estado: ' . $pedido->estado,
                    'estado_actual' => $pedido->estado
                ], 422);
            }

            // Restaurar stock anterior
            if ($detallePedido->variacion_id) {
                $detallePedido->variacion->increment('stock', $detallePedido->cantidad);
            } else {
                $detallePedido->producto->increment('stock', $detallePedido->cantidad);
            }

            // Si se cambió el producto o variación, obtener los nuevos
            $producto = isset($datosValidados['producto_id']) 
                ? Producto::findOrFail($datosValidados['producto_id'])
                : $detallePedido->producto;

            $variacion = null;
            if (isset($datosValidados['variacion_id'])) {
                $variacion = VariacionProducto::where('id', $datosValidados['variacion_id'])
                    ->where('producto_id', $producto->id)
                    ->firstOrFail();
            } elseif ($detallePedido->variacion_id && !isset($datosValidados['variacion_id'])) {
                // Mantener la variación actual si no se especifica una nueva
                $variacion = $detallePedido->variacion;
            }

            // Verificar stock disponible para la nueva cantidad
            $nuevaCantidad = $datosValidados['cantidad'] ?? $detallePedido->cantidad;
            $stockDisponible = $variacion ? $variacion->stock : $producto->stock;
            
            if ($stockDisponible < $nuevaCantidad) {
                // Restaurar el stock que acabamos de devolver
                if ($detallePedido->variacion_id) {
                    $detallePedido->variacion->decrement('stock', $detallePedido->cantidad);
                } else {
                    $detallePedido->producto->decrement('stock', $detallePedido->cantidad);
                }

                return response()->json([
                    'message' => 'Stock insuficiente.',
                    'stock_disponible' => $stockDisponible,
                    'cantidad_solicitada' => $nuevaCantidad
                ], 422);
            }

            // Calcular nuevos precios si es necesario
            if (isset($datosValidados['producto_id']) || isset($datosValidados['variacion_id']) || isset($datosValidados['cantidad'])) {
                $precioUnitario = $variacion 
                    ? ($variacion->precio_oferta ?? $variacion->precio)
                    : ($producto->precio_oferta ?? $producto->precio);

                $subtotal = $precioUnitario * $nuevaCantidad;
                $descuento = $datosValidados['descuento'] ?? $detallePedido->descuento ?? 0;
                $impuesto = ($subtotal - $descuento) * 0.18; // IGV 18%

                $datosValidados['precio_unitario'] = $precioUnitario;
                $datosValidados['subtotal'] = $subtotal;
                $datosValidados['impuesto'] = $impuesto;
            }

            // Actualizar el detalle
            $detallePedido->update($datosValidados);

            // Reducir stock con la nueva cantidad
            if ($variacion) {
                $variacion->decrement('stock', $nuevaCantidad);
            } else {
                $producto->decrement('stock', $nuevaCantidad);
            }

            // Recalcular total del pedido
            $this->recalcularTotalPedido($pedido);

            DB::commit();

            Log::info("Detalle de pedido actualizado exitosamente", [
                'detalle_id' => $detallePedido->id,
                'pedido_id' => $pedido->id,
                'cambios' => $datosValidados
            ]);

            return response()->json([
                'message' => 'Detalle de pedido actualizado exitosamente.',
                'data' => new DetallePedidoResource($detallePedido->fresh(['pedido.usuario', 'producto', 'variacion']))
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Recurso no encontrado.',
                'error' => $e->getMessage()
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar detalle de pedido ID {$detallePedido->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al actualizar el detalle de pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DetallePedido $detallePedido): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Verificar que el pedido está en estado editable
            $pedido = $detallePedido->pedido;
            
            if (!in_array($pedido->estado, ['pendiente', 'aprobado'])) {
                return response()->json([
                    'message' => 'No se pueden eliminar items de un pedido en estado: ' . $pedido->estado,
                    'estado_actual' => $pedido->estado
                ], 422);
            }

            // Verificar que no es el último item del pedido
            $cantidadItems = $pedido->detalles()->count();
            if ($cantidadItems <= 1) {
                return response()->json([
                    'message' => 'No se puede eliminar el último item del pedido. Elimine el pedido completo si es necesario.',
                    'items_restantes' => $cantidadItems
                ], 422);
            }

            // Restaurar stock
            if ($detallePedido->variacion_id) {
                $detallePedido->variacion->increment('stock', $detallePedido->cantidad);
            } else {
                $detallePedido->producto->increment('stock', $detallePedido->cantidad);
            }

            $detalleId = $detallePedido->id;
            $detallePedido->delete();

            // Recalcular total del pedido
            $this->recalcularTotalPedido($pedido);

            DB::commit();

            Log::info("Detalle de pedido eliminado exitosamente", [
                'detalle_id' => $detalleId,
                'pedido_id' => $pedido->id,
                'stock_restaurado' => true
            ]);

            return response()->json(null, 204);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar detalle de pedido ID {$detallePedido->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar el detalle de pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles por pedido específico
     */
    public function byPedido(Request $request, int $pedidoId): JsonResponse
    {
        try {
            $pedido = Pedido::findOrFail($pedidoId);
            
            $query = DetallePedido::with(['producto', 'variacion'])
                ->where('pedido_id', $pedidoId);

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'asc');
            
            $allowedSorts = ['created_at', 'cantidad', 'precio_unitario', 'subtotal'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection);
            }

            $detalles = $query->get();

            return response()->json([
                'data' => DetallePedidoResource::collection($detalles),
                'pedido' => [
                    'id' => $pedido->id,
                    'estado' => $pedido->estado,
                    'total' => $pedido->total,
                    'numero_items' => $detalles->sum('cantidad'),
                    'subtotal' => $detalles->sum('subtotal'),
                    'total_descuentos' => $detalles->sum('descuento'),
                    'total_impuestos' => $detalles->sum('impuesto'),
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
                'pedido_id' => $pedidoId
            ], 404);
        } catch (Exception $e) {
            Log::error("Error al obtener detalles del pedido ID {$pedidoId}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los detalles del pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de detalles de pedidos
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $fechaDesde = $request->get('fecha_desde');
            $fechaHasta = $request->get('fecha_hasta');

            $query = DetallePedido::with(['producto', 'variacion']);

            if ($fechaDesde) {
                $query->whereDate('created_at', '>=', $fechaDesde);
            }

            if ($fechaHasta) {
                $query->whereDate('created_at', '<=', $fechaHasta);
            }

            // Estadísticas generales
            $totalItems = $query->count();
            $cantidadTotal = $query->sum('cantidad');
            $ventasTotal = $query->sum('subtotal');
            $descuentosTotal = $query->sum('descuento');
            $impuestosTotal = $query->sum('impuesto');

            // Productos más vendidos
            $productosMasVendidos = DetallePedido::select('producto_id')
                ->selectRaw('SUM(cantidad) as total_vendido')
                ->selectRaw('SUM(subtotal) as total_ingresos')
                ->with('producto:id,nombre,sku')
                ->when($fechaDesde, fn($q) => $q->whereDate('created_at', '>=', $fechaDesde))
                ->when($fechaHasta, fn($q) => $q->whereDate('created_at', '<=', $fechaHasta))
                ->groupBy('producto_id')
                ->orderByDesc('total_vendido')
                ->limit(10)
                ->get();

            // Variaciones más vendidas
            $variacionesMasVendidas = DetallePedido::select('variacion_id')
                ->selectRaw('SUM(cantidad) as total_vendido')
                ->selectRaw('SUM(subtotal) as total_ingresos')
                ->with(['variacion:id,sku,precio', 'producto:id,nombre'])
                ->whereNotNull('variacion_id')
                ->when($fechaDesde, fn($q) => $q->whereDate('created_at', '>=', $fechaDesde))
                ->when($fechaHasta, fn($q) => $q->whereDate('created_at', '<=', $fechaHasta))
                ->groupBy('variacion_id')
                ->orderByDesc('total_vendido')
                ->limit(10)
                ->get();

            // Estadísticas por moneda
            $porMoneda = DetallePedido::select('moneda')
                ->selectRaw('COUNT(*) as cantidad_items')
                ->selectRaw('SUM(cantidad) as cantidad_productos')
                ->selectRaw('SUM(subtotal) as total_ventas')
                ->when($fechaDesde, fn($q) => $q->whereDate('created_at', '>=', $fechaDesde))
                ->when($fechaHasta, fn($q) => $q->whereDate('created_at', '<=', $fechaHasta))
                ->groupBy('moneda')
                ->get();

            // Promedio de cantidad por item
            $promediosCantidad = [
                'promedio_cantidad_por_item' => $totalItems > 0 ? round($cantidadTotal / $totalItems, 2) : 0,
                'promedio_precio_unitario' => $totalItems > 0 ? round($ventasTotal / $cantidadTotal, 2) : 0,
                'promedio_descuento_por_item' => $totalItems > 0 ? round($descuentosTotal / $totalItems, 2) : 0,
            ];

            return response()->json([
                'estadisticas' => [
                    'resumen' => [
                        'total_items' => $totalItems,
                        'cantidad_total_productos' => $cantidadTotal,
                        'ventas_total' => round($ventasTotal, 2),
                        'descuentos_total' => round($descuentosTotal, 2),
                        'impuestos_total' => round($impuestosTotal, 2),
                        'ventas_netas' => round($ventasTotal - $descuentosTotal + $impuestosTotal, 2),
                    ],
                    'promedios' => $promediosCantidad,
                    'productos_mas_vendidos' => $productosMasVendidos,
                    'variaciones_mas_vendidas' => $variacionesMasVendidas,
                    'por_moneda' => $porMoneda,
                ],
                'periodo' => [
                    'desde' => $fechaDesde ?? 'Sin límite',
                    'hasta' => $fechaHasta ?? 'Sin límite',
                    'total_dias' => $fechaDesde && $fechaHasta 
                        ? \Carbon\Carbon::parse($fechaDesde)->diffInDays(\Carbon\Carbon::parse($fechaHasta)) + 1
                        : null
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener estadísticas de detalles de pedidos: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener las estadísticas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar cantidad de un detalle específico
     */
    public function updateCantidad(Request $request, DetallePedido $detallePedido): JsonResponse
    {
        try {
            $request->validate([
                'cantidad' => 'required|integer|min:1|max:999',
            ]);

            DB::beginTransaction();

            // Verificar que el pedido está en estado editable
            $pedido = $detallePedido->pedido;
            
            if (!in_array($pedido->estado, ['pendiente', 'aprobado'])) {
                return response()->json([
                    'message' => 'No se puede modificar la cantidad de items en un pedido en estado: ' . $pedido->estado,
                    'estado_actual' => $pedido->estado
                ], 422);
            }

            $nuevaCantidad = $request->cantidad;
            $cantidadAnterior = $detallePedido->cantidad;
            $diferencia = $nuevaCantidad - $cantidadAnterior;

            // Verificar stock si se aumenta la cantidad
            if ($diferencia > 0) {
                $stockDisponible = $detallePedido->variacion_id 
                    ? $detallePedido->variacion->stock 
                    : $detallePedido->producto->stock;

                if ($stockDisponible < $diferencia) {
                    return response()->json([
                        'message' => 'Stock insuficiente para aumentar la cantidad.',
                        'stock_disponible' => $stockDisponible,
                        'cantidad_adicional_solicitada' => $diferencia
                    ], 422);
                }
            }

            // Actualizar stock
            if ($detallePedido->variacion_id) {
                $detallePedido->variacion->decrement('stock', $diferencia);
            } else {
                $detallePedido->producto->decrement('stock', $diferencia);
            }

            // Recalcular subtotal e impuesto
            $subtotal = $detallePedido->precio_unitario * $nuevaCantidad;
            $impuesto = ($subtotal - ($detallePedido->descuento ?? 0)) * 0.18;

            // Actualizar el detalle
            $detallePedido->update([
                'cantidad' => $nuevaCantidad,
                'subtotal' => $subtotal,
                'impuesto' => $impuesto,
            ]);

            // Recalcular total del pedido
            $this->recalcularTotalPedido($pedido);

            DB::commit();

            Log::info("Cantidad de detalle de pedido actualizada", [
                'detalle_id' => $detallePedido->id,
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_nueva' => $nuevaCantidad,
                'diferencia' => $diferencia
            ]);

            return response()->json([
                'message' => 'Cantidad actualizada exitosamente.',
                'data' => new DetallePedidoResource($detallePedido->fresh(['pedido.usuario', 'producto', 'variacion']))
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar cantidad del detalle ID {$detallePedido->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al actualizar la cantidad.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método privado para recalcular el total del pedido
     */
    private function recalcularTotalPedido(Pedido $pedido): void
    {
        $detalles = $pedido->detalles;
        
        $subtotalTotal = $detalles->sum('subtotal');
        $descuentoTotal = $detalles->sum('descuento') + ($pedido->descuento_total ?? 0);
        $impuestoTotal = $detalles->sum('impuesto');
        
        $nuevoTotal = $subtotalTotal - $descuentoTotal + $impuestoTotal;

        // Si es crédito, recalcular cuotas
        if ($pedido->tipo_pago === 'credito' && $pedido->cuotas) {
            $tasaInteres = 0.08; // 8% anual
            $interesTotal = ($nuevoTotal * $tasaInteres * $pedido->cuotas) / 12;
            $montoCuota = ($nuevoTotal + $interesTotal) / $pedido->cuotas;
            
            $pedido->update([
                'total' => $nuevoTotal,
                'interes_total' => $interesTotal,
                'monto_cuota' => $montoCuota,
            ]);
        } else {
            $pedido->update(['total' => $nuevoTotal]);
        }

        Log::info("Total del pedido recalculado", [
            'pedido_id' => $pedido->id,
            'nuevo_total' => $nuevoTotal,
            'subtotal' => $subtotalTotal,
            'descuentos' => $descuentoTotal,
            'impuestos' => $impuestoTotal
        ]);
    }
} 