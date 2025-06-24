<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePedidoRequest;
use App\Http\Requests\UpdatePedidoRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\User;
use App\Models\Producto;
use App\Models\VariacionProducto;
use App\Models\Cupon;
use App\Models\ZonaReparto;
use App\Models\DireccionValidada;
use App\Models\ProgramacionEntrega;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;

class PedidoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Pedido::with([
                'user', 
                'user.cliente', 
                'detalles.producto', 
                'detalles.variacion',
                'metodoPago',
                'zonaReparto',
                'direccionValidada',
                'repartidor'
            ]);

            // Filtro por usuario (para clientes ver solo sus pedidos)
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filtro por estado
            if ($request->filled('estado')) {
                $estados = is_array($request->estado) ? $request->estado : [$request->estado];
                $query->whereIn('estado', $estados);
            }

            // Filtro por tipo de pago
            if ($request->filled('tipo_pago')) {
                $tiposPago = is_array($request->tipo_pago) ? $request->tipo_pago : [$request->tipo_pago];
                $query->whereIn('tipo_pago', $tiposPago);
            }

            // Filtro por tipo de entrega
            if ($request->filled('tipo_entrega')) {
                $tiposEntrega = is_array($request->tipo_entrega) ? $request->tipo_entrega : [$request->tipo_entrega];
                $query->whereIn('tipo_entrega', $tiposEntrega);
            }

            // Filtro por zona de reparto
            if ($request->filled('zona_reparto_id')) {
                $query->where('zona_reparto_id', $request->zona_reparto_id);
            }

            // Filtro por repartidor
            if ($request->filled('repartidor_id')) {
                $query->where('repartidor_id', $request->repartidor_id);
            }

            // Filtro por rango de fechas
            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            // Filtro por fecha de entrega programada
            if ($request->filled('fecha_entrega_desde')) {
                $query->whereDate('fecha_entrega_programada', '>=', $request->fecha_entrega_desde);
            }
            if ($request->filled('fecha_entrega_hasta')) {
                $query->whereDate('fecha_entrega_programada', '<=', $request->fecha_entrega_hasta);
            }

            // Filtro por rango de totales
            if ($request->filled('total_min')) {
                $query->where('total', '>=', $request->total_min);
            }
            if ($request->filled('total_max')) {
                $query->where('total', '<=', $request->total_max);
            }

            // Filtro por canal de venta
            if ($request->filled('canal_venta')) {
                $query->where('canal_venta', $request->canal_venta);
            }

            // Búsqueda por código de rastreo
            if ($request->filled('codigo_rastreo')) {
                $query->where('codigo_rastreo', 'like', '%' . $request->codigo_rastreo . '%');
            }

            // Búsqueda por número de pedido
            if ($request->filled('numero_pedido')) {
                $query->where('numero_pedido', 'like', '%' . $request->numero_pedido . '%');
            }

            // Búsqueda general
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('observaciones', 'like', "%{$search}%")
                      ->orWhere('codigo_rastreo', 'like', "%{$search}%")
                      ->orWhere('numero_pedido', 'like', "%{$search}%")
                      ->orWhere('direccion_entrega', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('nombre', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      })
                      ->orWhereHas('repartidor', function ($repartidorQuery) use ($search) {
                          $repartidorQuery->where('nombre', 'like', "%{$search}%")
                                         ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $allowedSorts = [
                'created_at', 'updated_at', 'total', 'estado', 'tipo_pago', 
                'tipo_entrega', 'fecha_entrega_programada', 'numero_pedido'
            ];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Paginación
            $perPage = min($request->get('per_page', 15), 100);
            $pedidos = $query->paginate($perPage);

            return response()->json([
                'data' => PedidoResource::collection($pedidos->items()),
                'meta' => [
                    'current_page' => $pedidos->currentPage(),
                    'last_page' => $pedidos->lastPage(),
                    'per_page' => $pedidos->perPage(),
                    'total' => $pedidos->total(),
                    'from' => $pedidos->firstItem(),
                    'to' => $pedidos->lastItem(),
                ],
                'links' => [
                    'first' => $pedidos->url(1),
                    'last' => $pedidos->url($pedidos->lastPage()),
                    'prev' => $pedidos->previousPageUrl(),
                    'next' => $pedidos->nextPageUrl(),
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener pedidos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los pedidos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePedidoRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $datosValidados = $request->validated();
            
            // Validar zona de reparto si es delivery
            $zonaRepartoId = null;
            $direccionValidadaId = null;
            $tiempoEntregaEstimado = null;
            $costoEnvio = 0;

            if (($datosValidados['tipo_entrega'] ?? 'delivery') === 'delivery') {
                if (!isset($datosValidados['direccion_entrega'])) {
                    throw new Exception('Se requiere dirección de entrega para delivery');
                }

                // Buscar o validar dirección si se proporciona dirección validada
                if (isset($datosValidados['direccion_validada_id'])) {
                    $direccionValidada = DireccionValidada::with('zonaReparto')
                        ->findOrFail($datosValidados['direccion_validada_id']);
                    
                    $direccionValidadaId = $direccionValidada->id;
                    $zonaRepartoId = $direccionValidada->zona_reparto_id;
                    $costoEnvio = $direccionValidada->costo_envio_calculado ?? 0;
                    $tiempoEntregaEstimado = $direccionValidada->zonaReparto?->tiempo_entrega_promedio;
                } elseif (isset($datosValidados['zona_reparto_id'])) {
                    $zonaReparto = ZonaReparto::findOrFail($datosValidados['zona_reparto_id']);
                    $zonaRepartoId = $zonaReparto->id;
                    $costoEnvio = $zonaReparto->costo_envio;
                    $tiempoEntregaEstimado = $zonaReparto->tiempo_entrega_promedio;
                }
            }

            // Crear el pedido principal
            $pedido = Pedido::create([
                'user_id' => $datosValidados['user_id'],
                'metodo_pago_id' => $datosValidados['metodo_pago_id'] ?? null,
                'zona_reparto_id' => $zonaRepartoId,
                'direccion_validada_id' => $direccionValidadaId,
                'estado' => 'pendiente',
                'tipo_pago' => $datosValidados['tipo_pago'],
                'tipo_entrega' => $datosValidados['tipo_entrega'] ?? 'delivery',
                'cuotas' => $datosValidados['cuotas'] ?? null,
                'observaciones' => $datosValidados['observaciones'] ?? null,
                'canal_venta' => $datosValidados['canal_venta'] ?? 'web',
                'moneda' => $datosValidados['moneda'] ?? 'PEN',
                'costo_envio' => $costoEnvio,
                'tiempo_entrega_estimado' => $tiempoEntregaEstimado,
                'fecha_entrega_programada' => $datosValidados['fecha_entrega_programada'] ?? null,
                'direccion_entrega' => $datosValidados['direccion_entrega'] ?? null,
                'telefono_entrega' => $datosValidados['telefono_entrega'] ?? null,
                'referencia_entrega' => $datosValidados['referencia_entrega'] ?? null,
                'latitud_entrega' => $datosValidados['latitud_entrega'] ?? null,
                'longitud_entrega' => $datosValidados['longitud_entrega'] ?? null,
                'datos_envio' => $datosValidados['datos_envio'] ?? null,
                'metodo_envio' => $datosValidados['metodo_envio'] ?? null,
                'datos_cliente' => $datosValidados['datos_cliente'] ?? null,
                'cupon_codigo' => $datosValidados['cupon_codigo'] ?? null,
                'total' => 0, // Se calculará después
            ]);

            $subtotalPedido = 0;
            $descuentoTotal = 0;
            $igvTotal = 0;

            // Procesar cada item del pedido
            foreach ($datosValidados['items'] as $item) {
                // Obtener producto o variación
                if (isset($item['variacion_id'])) {
                    $variacion = VariacionProducto::findOrFail($item['variacion_id']);
                    $producto = $variacion->producto;
                    $precioUnitario = $variacion->precio_oferta ?? $variacion->precio;
                    
                    // Verificar stock de variación
                    if ($variacion->stock < $item['cantidad']) {
                        throw new Exception("Stock insuficiente para la variación {$variacion->sku}. Stock disponible: {$variacion->stock}");
                    }
                    
                    // Reducir stock de variación
                    $variacion->decrement('stock', $item['cantidad']);
                    
                } else {
                    $producto = Producto::findOrFail($item['producto_id']);
                    $precioUnitario = $producto->precio_oferta ?? $producto->precio;
                    $variacion = null;
                    
                    // Verificar stock de producto
                    if ($producto->stock < $item['cantidad']) {
                        throw new Exception("Stock insuficiente para el producto {$producto->sku}. Stock disponible: {$producto->stock}");
                    }
                    
                    // Reducir stock de producto
                    $producto->decrement('stock', $item['cantidad']);
                }

                $subtotal = $precioUnitario * $item['cantidad'];
                $descuentoItem = $item['descuento'] ?? 0;
                $baseImponible = $subtotal - $descuentoItem;
                $igv = $baseImponible * 0.18; // IGV 18%

                // Crear detalle del pedido
                DetallePedido::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $producto->id,
                    'variacion_id' => $variacion?->id,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotal,
                    'descuento' => $descuentoItem,
                    'impuesto' => $igv,
                    'moneda' => $datosValidados['moneda'] ?? 'PEN',
                ]);

                $subtotalPedido += $subtotal;
                $descuentoTotal += $descuentoItem;
                $igvTotal += $igv;
            }

            // Aplicar cupón si existe
            $descuentoCupon = 0;
            if (isset($datosValidados['cupon_codigo'])) {
                $cupon = Cupon::where('codigo', $datosValidados['cupon_codigo'])
                    ->activos()
                    ->vigentes()
                    ->disponibles()
                    ->first();

                if ($cupon) {
                    $descuentoCupon = $cupon->tipo === 'porcentaje' 
                        ? (($subtotalPedido - $descuentoTotal) * $cupon->descuento / 100)
                        : $cupon->descuento;
                    
                    $descuentoTotal += $descuentoCupon;
                    $cupon->increment('usos');
                    
                    // Registrar uso del cupón
                    $cupon->usuarios()->attach($datosValidados['user_id'], [
                        'usado' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Calcular totales finales
            $baseImponibleFinal = $subtotalPedido - $descuentoTotal;
            $igvFinal = $baseImponibleFinal * 0.18;
            $totalPedido = $baseImponibleFinal + $igvFinal + $costoEnvio;

            // Calcular cuotas si es crédito
            $interesTotal = 0;
            $montoCuota = null;
            
            if ($datosValidados['tipo_pago'] === 'credito' && isset($datosValidados['cuotas'])) {
                $tasaInteres = 0.08; // 8% anual
                $interesTotal = ($totalPedido * $tasaInteres * $datosValidados['cuotas']) / 12;
                $montoCuota = ($totalPedido + $interesTotal) / $datosValidados['cuotas'];
            }

            // Actualizar totales del pedido
            $pedido->update([
                'subtotal' => $subtotalPedido,
                'descuento' => $descuentoCupon,
                'descuento_total' => $descuentoTotal,
                'igv' => $igvFinal,
                'total' => $totalPedido,
                'interes_total' => $interesTotal,
                'monto_cuota' => $montoCuota,
            ]);

            DB::commit();

            Log::info("Pedido creado exitosamente", [
                'pedido_id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'user_id' => $datosValidados['user_id'],
                'total' => $totalPedido,
                'tipo_entrega' => $pedido->tipo_entrega,
                'zona_reparto_id' => $zonaRepartoId,
                'items_count' => count($datosValidados['items'])
            ]);

            return (new PedidoResource($pedido->load([
                'user', 'user.cliente', 'detalles.producto', 'detalles.variacion',
                'metodoPago', 'zonaReparto', 'direccionValidada', 'repartidor'
            ])))->response()->setStatusCode(201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al crear pedido: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al crear el pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Pedido $pedido): JsonResponse
    {
        try {
            $pedido->load([
                'user', 'user.cliente', 'detalles.producto', 'detalles.variacion', 
                'detalles.detalleAdicionales.adicional', 'pagos.metodoPago', 'cuotasCredito',
                'metodoPago', 'zonaReparto', 'direccionValidada', 'repartidor',
                'seguimientos.usuarioCambio', 'programacionEntrega'
            ]);
            
            return (new PedidoResource($pedido))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error("Error al obtener pedido ID {$pedido->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener el pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePedidoRequest $request, Pedido $pedido): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $datosValidados = $request->validated();
            
            // Verificar si el pedido puede ser modificado
            if (in_array($pedido->estado, ['entregado', 'cancelado', 'devuelto'])) {
                return response()->json([
                    'message' => 'No se puede modificar un pedido en estado: ' . $pedido->estado
                ], 422);
            }

            // Validar cambios de zona de reparto o dirección si es delivery
            if (isset($datosValidados['direccion_validada_id']) && $pedido->direccion_validada_id !== $datosValidados['direccion_validada_id']) {
                $direccionValidada = DireccionValidada::with('zonaReparto')
                    ->findOrFail($datosValidados['direccion_validada_id']);
                
                $datosValidados['zona_reparto_id'] = $direccionValidada->zona_reparto_id;
                $datosValidados['costo_envio'] = $direccionValidada->costo_envio_calculado ?? 0;
                $datosValidados['tiempo_entrega_estimado'] = $direccionValidada->zonaReparto?->tiempo_entrega_promedio;
            }

            $pedido->update($datosValidados);
            
            DB::commit();

            Log::info("Pedido actualizado exitosamente", [
                'pedido_id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'cambios' => array_keys($datosValidados)
            ]);

            return (new PedidoResource($pedido->load([
                'user', 'user.cliente', 'detalles.producto', 'detalles.variacion',
                'metodoPago', 'zonaReparto', 'direccionValidada', 'repartidor'
            ])))->response()->setStatusCode(200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar pedido ID {$pedido->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al actualizar el pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pedido $pedido): JsonResponse
    {
        try {
            // Solo permitir eliminación si está en estado pendiente
            if ($pedido->estado !== 'pendiente') {
                return response()->json([
                    'message' => 'Solo se pueden eliminar pedidos en estado pendiente.',
                    'estado_actual' => $pedido->estado
                ], 422);
            }

            DB::beginTransaction();

            // Restaurar stock de productos/variaciones
            foreach ($pedido->detalles as $detalle) {
                if ($detalle->variacion_id) {
                    $detalle->variacion->increment('stock', $detalle->cantidad);
                } else {
                    $detalle->producto->increment('stock', $detalle->cantidad);
                }
            }

            $pedidoId = $pedido->id;
            $numeroPedido = $pedido->numero_pedido;
            $pedido->delete(); // Soft delete

            DB::commit();

            Log::info("Pedido eliminado exitosamente", [
                'pedido_id' => $pedidoId,
                'numero_pedido' => $numeroPedido,
                'stock_restaurado' => true
            ]);

            return response()->json(null, 204);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar pedido ID {$pedido->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar el pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado del pedido
     */
    public function cambiarEstado(Request $request, Pedido $pedido): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:pendiente,aprobado,rechazado,en_proceso,enviado,entregado,cancelado,devuelto',
            'observaciones' => 'nullable|string|max:1000',
            'codigo_rastreo' => 'nullable|string|max:100',
            'repartidor_id' => 'nullable|exists:users,id',
            'fecha_entrega_real' => 'nullable|date'
        ]);

        try {
            DB::beginTransaction();

            $estadoAnterior = $pedido->estado;
            $nuevoEstado = $request->estado;

            // Validar transiciones de estado válidas
            $transicionesValidas = [
                'pendiente' => ['aprobado', 'rechazado', 'cancelado'],
                'aprobado' => ['en_proceso', 'cancelado'],
                'en_proceso' => ['enviado', 'cancelado'],
                'enviado' => ['entregado', 'devuelto'],
                'entregado' => ['devuelto'],
                'rechazado' => [],
                'cancelado' => [],
                'devuelto' => []
            ];

            if (!in_array($nuevoEstado, $transicionesValidas[$estadoAnterior])) {
                return response()->json([
                    'message' => "No se puede cambiar de estado '{$estadoAnterior}' a '{$nuevoEstado}'",
                    'transiciones_validas' => $transicionesValidas[$estadoAnterior]
                ], 422);
            }

            // Preparar datos de actualización
            $datosActualizacion = [
                'estado' => $nuevoEstado,
                'observaciones' => $request->observaciones ?? $pedido->observaciones,
                'codigo_rastreo' => $request->codigo_rastreo ?? $pedido->codigo_rastreo,
            ];

            // Lógica específica por estado
            switch ($nuevoEstado) {
                case 'enviado':
                    if (!$request->codigo_rastreo && !$pedido->codigo_rastreo) {
                        return response()->json([
                            'message' => 'Se requiere código de rastreo para marcar como enviado'
                        ], 422);
                    }
                    
                    // Asignar repartidor si se proporciona
                    if ($request->repartidor_id) {
                        $datosActualizacion['repartidor_id'] = $request->repartidor_id;
                    }
                    break;

                case 'entregado':
                    // Registrar fecha de entrega real
                    $datosActualizacion['fecha_entrega_real'] = $request->fecha_entrega_real ?? now();
                    break;

                case 'cancelado':
                case 'devuelto':
                    // Restaurar stock
                    foreach ($pedido->detalles as $detalle) {
                        if ($detalle->variacion_id) {
                            $detalle->variacion->increment('stock', $detalle->cantidad);
                        } else {
                            $detalle->producto->increment('stock', $detalle->cantidad);
                        }
                    }
                    
                    // Limpiar repartidor asignado
                    $datosActualizacion['repartidor_id'] = null;
                    break;
            }

            // Actualizar pedido
            $pedido->update($datosActualizacion);

            // Registrar en seguimiento del pedido
            $pedido->seguimientos()->create([
                'estado_anterior' => $estadoAnterior,
                'estado_actual' => $nuevoEstado,
                'observaciones' => $request->observaciones,
                'fecha_cambio' => now(),
                'usuario_cambio_id' => Auth::id(),
            ]);

            DB::commit();

            Log::info("Estado de pedido cambiado", [
                'pedido_id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'estado_anterior' => $estadoAnterior,
                'nuevo_estado' => $nuevoEstado,
                'codigo_rastreo' => $pedido->codigo_rastreo,
                'repartidor_id' => $pedido->repartidor_id,
                'usuario_cambio' => Auth::id()
            ]);

            return (new PedidoResource($pedido->load([
                'user', 'user.cliente', 'detalles.producto', 'detalles.variacion',
                'metodoPago', 'zonaReparto', 'direccionValidada', 'repartidor',
                'seguimientos.usuarioCambio'
            ])))->response()->setStatusCode(200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al cambiar estado del pedido ID {$pedido->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al cambiar el estado.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener pedidos por usuario
     */
    public function byUsuario(User $usuario, Request $request): JsonResponse
    {
        try {
            $query = $usuario->pedidos()->with([
                'user.cliente', 'detalles.producto', 'detalles.variacion',
                'metodoPago', 'zonaReparto', 'direccionValidada', 'repartidor'
            ]);

            // Aplicar filtros similares al index
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('tipo_pago')) {
                $query->where('tipo_pago', $request->tipo_pago);
            }

            if ($request->filled('tipo_entrega')) {
                $query->where('tipo_entrega', $request->tipo_entrega);
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $perPage = min($request->get('per_page', 15), 100);
            $pedidos = $query->paginate($perPage);

            return response()->json([
                'data' => PedidoResource::collection($pedidos->items()),
                'meta' => [
                    'current_page' => $pedidos->currentPage(),
                    'last_page' => $pedidos->lastPage(),
                    'per_page' => $pedidos->perPage(),
                    'total' => $pedidos->total(),
                ],
                'usuario' => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre ?? $usuario->name,
                    'email' => $usuario->email,
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener pedidos del usuario ID {$usuario->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los pedidos del usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de pedidos
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $fechaDesde = $request->get('fecha_desde', now()->subDays(30)->format('Y-m-d'));
            $fechaHasta = $request->get('fecha_hasta', now()->format('Y-m-d'));

            $baseQuery = Pedido::whereBetween('created_at', [$fechaDesde, $fechaHasta]);

            $estadisticas = [
                'resumen' => [
                    'total_pedidos' => (clone $baseQuery)->count(),
                    'total_ventas' => (clone $baseQuery)->sum('total'),
                    'ticket_promedio' => (clone $baseQuery)->avg('total'),
                    'pedidos_pendientes' => (clone $baseQuery)->where('estado', 'pendiente')->count(),
                    'pedidos_entregados' => (clone $baseQuery)->where('estado', 'entregado')->count(),
                    'total_deliveries' => (clone $baseQuery)->where('tipo_entrega', 'delivery')->count(),
                    'total_recojos' => (clone $baseQuery)->where('tipo_entrega', 'recojo_tienda')->count(),
                    'ingresos_envio' => (clone $baseQuery)->sum('costo_envio'),
                ],
                'por_estado' => (clone $baseQuery)
                    ->select('estado', DB::raw('count(*) as cantidad'), DB::raw('sum(total) as total_ventas'))
                    ->groupBy('estado')
                    ->get(),
                'por_tipo_pago' => (clone $baseQuery)
                    ->select('tipo_pago', DB::raw('count(*) as cantidad'), DB::raw('sum(total) as total_ventas'))
                    ->groupBy('tipo_pago')
                    ->get(),
                'por_tipo_entrega' => (clone $baseQuery)
                    ->select('tipo_entrega', DB::raw('count(*) as cantidad'), DB::raw('sum(total) as total_ventas'), DB::raw('sum(costo_envio) as total_envios'))
                    ->groupBy('tipo_entrega')
                    ->get(),
                'por_zona_reparto' => (clone $baseQuery)
                    ->whereNotNull('zona_reparto_id')
                    ->with('zonaReparto:id,nombre')
                    ->select('zona_reparto_id', DB::raw('count(*) as cantidad'), DB::raw('sum(total) as total_ventas'), DB::raw('avg(tiempo_entrega_estimado) as tiempo_promedio'))
                    ->groupBy('zona_reparto_id')
                    ->get(),
                'por_canal_venta' => (clone $baseQuery)
                    ->select('canal_venta', DB::raw('count(*) as cantidad'), DB::raw('sum(total) as total_ventas'))
                    ->groupBy('canal_venta')
                    ->get(),
                'ventas_diarias' => (clone $baseQuery)
                    ->select(DB::raw('DATE(created_at) as fecha'), DB::raw('count(*) as pedidos'), DB::raw('sum(total) as ventas'), DB::raw('sum(costo_envio) as envios'))
                    ->groupBy(DB::raw('DATE(created_at)'))
                    ->orderBy('fecha')
                    ->get(),
                'top_clientes' => User::withCount('pedidos')
                    ->withSum('pedidos', 'total')
                    ->having('pedidos_count', '>', 0)
                    ->orderBy('pedidos_sum_total', 'desc')
                    ->limit(10)
                    ->get(['id', 'nombre', 'email', 'pedidos_count', 'pedidos_sum_total']),
                'top_repartidores' => User::whereHas('pedidosComoRepartidor', function ($query) use ($fechaDesde, $fechaHasta) {
                        $query->whereBetween('created_at', [$fechaDesde, $fechaHasta]);
                    })
                    ->withCount(['pedidosComoRepartidor' => function ($query) use ($fechaDesde, $fechaHasta) {
                        $query->whereBetween('created_at', [$fechaDesde, $fechaHasta]);
                    }])
                    ->orderBy('pedidos_como_repartidor_count', 'desc')
                    ->limit(10)
                    ->get(['id', 'nombre', 'email', 'pedidos_como_repartidor_count']),
                'productos_mas_vendidos' => DetallePedido::select('producto_id')
                    ->with('producto:id,nombre,sku')
                    ->whereHas('pedido', function ($query) use ($fechaDesde, $fechaHasta) {
                        $query->whereBetween('created_at', [$fechaDesde, $fechaHasta]);
                    })
                    ->selectRaw('producto_id, sum(cantidad) as total_vendido, sum(subtotal) as total_ingresos')
                    ->groupBy('producto_id')
                    ->orderBy('total_vendido', 'desc')
                    ->limit(10)
                    ->get(),
                'tiempos_entrega' => [
                    'promedio_general' => (clone $baseQuery)->whereNotNull('tiempo_entrega_estimado')->avg('tiempo_entrega_estimado'),
                    'promedio_delivery' => (clone $baseQuery)->where('tipo_entrega', 'delivery')->whereNotNull('tiempo_entrega_estimado')->avg('tiempo_entrega_estimado'),
                    'cumplimiento_entregas' => [
                        'total_programadas' => (clone $baseQuery)->whereNotNull('fecha_entrega_programada')->count(),
                        'entregadas_a_tiempo' => (clone $baseQuery)->whereNotNull('fecha_entrega_programada')->whereNotNull('fecha_entrega_real')
                            ->whereRaw('fecha_entrega_real <= fecha_entrega_programada')->count(),
                    ]
                ],
            ];

            return response()->json([
                'estadisticas' => $estadisticas,
                'periodo' => [
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                    'dias' => now()->parse($fechaDesde)->diffInDays(now()->parse($fechaHasta)) + 1
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas de pedidos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener las estadísticas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aplicar cupón a un pedido
     */
    public function aplicarCupon(Request $request, Pedido $pedido): JsonResponse
    {
        $request->validate([
            'codigo_cupon' => 'required|string|exists:cupones,codigo'
        ]);

        try {
            if ($pedido->estado !== 'pendiente') {
                return response()->json([
                    'message' => 'Solo se puede aplicar cupón a pedidos pendientes'
                ], 422);
            }

            $cupon = Cupon::where('codigo', $request->codigo_cupon)
                ->activos()
                ->vigentes()
                ->disponibles()
                ->first();

            if (!$cupon) {
                return response()->json([
                    'message' => 'Cupón no válido, expirado o agotado'
                ], 422);
            }

            DB::beginTransaction();

            // Calcular descuento
            $descuentoCupon = $cupon->tipo === 'porcentaje' 
                ? ($pedido->total * $cupon->descuento / 100)
                : $cupon->descuento;

            $nuevoTotal = max(0, $pedido->total - $descuentoCupon);
            $descuentoTotalAnterior = $pedido->descuento_total ?? 0;

            $pedido->update([
                'total' => $nuevoTotal,
                'descuento_total' => $descuentoTotalAnterior + $descuentoCupon,
            ]);

            // Registrar uso del cupón
            $cupon->increment('usos');
            $cupon->usuarios()->attach($pedido->user_id, [
                'usado' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            Log::info("Cupón aplicado al pedido", [
                'pedido_id' => $pedido->id,
                'codigo_cupon' => $request->codigo_cupon,
                'descuento_aplicado' => $descuentoCupon,
                'nuevo_total' => $nuevoTotal
            ]);

            return response()->json([
                'message' => 'Cupón aplicado exitosamente',
                'descuento_aplicado' => $descuentoCupon,
                'total_anterior' => $pedido->total + $descuentoCupon,
                'nuevo_total' => $nuevoTotal,
                'pedido' => new PedidoResource($pedido->load([
                    'user', 'user.cliente', 'detalles.producto', 'detalles.variacion',
                    'metodoPago', 'zonaReparto', 'direccionValidada', 'repartidor'
                ]))
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al aplicar cupón al pedido ID {$pedido->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al aplicar el cupón.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Asignar repartidor a un pedido
     */
    public function asignarRepartidor(Request $request, Pedido $pedido): JsonResponse
    {
        $request->validate([
            'repartidor_id' => 'required|exists:users,id',
            'fecha_programada' => 'nullable|date|after:now',
            'hora_inicio_ventana' => 'nullable|date_format:H:i',
            'hora_fin_ventana' => 'nullable|date_format:H:i|after:hora_inicio_ventana',
        ]);

        try {
            if (!in_array($pedido->estado, ['aprobado', 'en_proceso'])) {
                return response()->json([
                    'message' => 'Solo se puede asignar repartidor a pedidos aprobados o en proceso',
                    'estado_actual' => $pedido->estado
                ], 422);
            }

            if ($pedido->tipo_entrega !== 'delivery') {
                return response()->json([
                    'message' => 'Solo se puede asignar repartidor a pedidos de delivery'
                ], 422);
            }

            DB::beginTransaction();

            // Actualizar repartidor en el pedido
            $pedido->update([
                'repartidor_id' => $request->repartidor_id,
                'fecha_entrega_programada' => $request->fecha_programada ?? $pedido->fecha_entrega_programada,
            ]);

            // Crear o actualizar programación de entrega
            if ($request->filled('fecha_programada') || $request->filled('hora_inicio_ventana')) {
                $pedido->programacionEntrega()->updateOrCreate(
                    ['pedido_id' => $pedido->id],
                    [
                        'repartidor_id' => $request->repartidor_id,
                        'fecha_programada' => $request->fecha_programada ?? now()->addHours(2),
                        'hora_inicio_ventana' => $request->hora_inicio_ventana,
                        'hora_fin_ventana' => $request->hora_fin_ventana,
                        'estado' => 'programado',
                    ]
                );
            }

            DB::commit();

            Log::info("Repartidor asignado al pedido", [
                'pedido_id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'repartidor_id' => $request->repartidor_id,
                'fecha_programada' => $request->fecha_programada
            ]);

            return (new PedidoResource($pedido->load([
                'user', 'user.cliente', 'detalles.producto', 'detalles.variacion',
                'metodoPago', 'zonaReparto', 'direccionValidada', 'repartidor',
                'programacionEntrega'
            ])))->response()->setStatusCode(200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al asignar repartidor al pedido ID {$pedido->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al asignar el repartidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener pedidos por repartidor
     */
    public function porRepartidor(Request $request): JsonResponse
    {
        $request->validate([
            'repartidor_id' => 'required|exists:users,id',
            'fecha' => 'nullable|date',
            'estado' => 'nullable|in:enviado,entregado,devuelto',
        ]);

        try {
            $query = Pedido::with([
                'user', 'user.cliente', 'detalles.producto', 'detalles.variacion',
                'metodoPago', 'zonaReparto', 'direccionValidada',
                'programacionEntrega'
            ])->where('repartidor_id', $request->repartidor_id);

            if ($request->filled('fecha')) {
                $query->whereDate('fecha_entrega_programada', $request->fecha);
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            } else {
                $query->whereIn('estado', ['enviado', 'entregado']);
            }

            $pedidos = $query->orderBy('fecha_entrega_programada')->get();

            return response()->json([
                'data' => PedidoResource::collection($pedidos),
                'resumen' => [
                    'total_pedidos' => $pedidos->count(),
                    'pedidos_enviados' => $pedidos->where('estado', 'enviado')->count(),
                    'pedidos_entregados' => $pedidos->where('estado', 'entregado')->count(),
                    'total_ventas' => $pedidos->sum('total'),
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener pedidos del repartidor: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los pedidos del repartidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rastrear pedido por código
     */
    public function rastrear(Request $request): JsonResponse
    {
        $request->validate([
            'codigo_rastreo' => 'required|string',
        ]);

        try {
            $pedido = Pedido::with([
                'user:id,nombre,email', 
                'zonaReparto:id,nombre,tiempo_entrega_min,tiempo_entrega_max',
                'repartidor:id,nombre,telefono',
                'seguimientos.usuarioCambio:id,nombre'
            ])->where('codigo_rastreo', $request->codigo_rastreo)->first();

            if (!$pedido) {
                return response()->json([
                    'message' => 'No se encontró ningún pedido con ese código de rastreo'
                ], 404);
            }

            return response()->json([
                'pedido' => [
                    'id' => $pedido->id,
                    'numero_pedido' => $pedido->numero_pedido,
                    'estado' => $pedido->estado,
                    'fecha_pedido' => $pedido->created_at->toISOString(),
                    'fecha_entrega_programada' => $pedido->fecha_entrega_programada?->toISOString(),
                    'fecha_entrega_real' => $pedido->fecha_entrega_real?->toISOString(),
                    'tipo_entrega' => $pedido->tipo_entrega,
                    'direccion_entrega' => $pedido->direccion_entrega,
                    'tiempo_entrega_estimado' => $pedido->tiempo_entrega_estimado,
                    'zona_reparto' => $pedido->zonaReparto,
                    'repartidor' => $pedido->repartidor,
                ],
                'seguimiento' => $pedido->seguimientos->map(function ($seguimiento) {
                    return [
                        'estado_anterior' => $seguimiento->estado_anterior,
                        'estado_actual' => $seguimiento->estado_actual,
                        'fecha_cambio' => $seguimiento->fecha_cambio->toISOString(),
                        'observaciones' => $seguimiento->observaciones,
                        'usuario_cambio' => $seguimiento->usuarioCambio?->nombre,
                    ];
                })->sortBy('fecha_cambio')->values(),
            ]);

        } catch (Exception $e) {
            Log::error("Error al rastrear pedido: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al rastrear el pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener pedidos por zona de reparto
     */
    public function porZona(Request $request): JsonResponse
    {
        $request->validate([
            'zona_reparto_id' => 'required|exists:zonas_reparto,id',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'estado' => 'nullable|string',
        ]);

        try {
            $query = Pedido::with([
                'user:id,nombre,email', 
                'repartidor:id,nombre,telefono',
                'detalles.producto:id,nombre'
            ])->where('zona_reparto_id', $request->zona_reparto_id);

            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            $pedidos = $query->orderBy('created_at', 'desc')->get();

            $estadisticas = [
                'total_pedidos' => $pedidos->count(),
                'total_ventas' => $pedidos->sum('total'),
                'ingresos_envio' => $pedidos->sum('costo_envio'),
                'pedidos_por_estado' => $pedidos->groupBy('estado')->map->count(),
                'tiempo_entrega_promedio' => $pedidos->where('tiempo_entrega_estimado', '>', 0)->avg('tiempo_entrega_estimado'),
            ];

            return response()->json([
                'data' => PedidoResource::collection($pedidos),
                'estadisticas' => $estadisticas,
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener pedidos por zona: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los pedidos por zona.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 