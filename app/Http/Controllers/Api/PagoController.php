<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePagoRequest;
use App\Http\Requests\UpdatePagoRequest;
use App\Http\Resources\PagoResource;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\CuotaCredito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class PagoController extends Controller
{
    /**
     * Display a listing of the resource with advanced filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Pago::with(['pedido.user', 'metodoPago']);

            // Aplicar filtros
            $this->aplicarFiltros($query, $request);

            // Aplicar búsqueda
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('referencia', 'like', "%{$searchTerm}%")
                      ->orWhere('metodo', 'like', "%{$searchTerm}%")
                      ->orWhereHas('pedido', function ($pedidoQuery) use ($searchTerm) {
                          $pedidoQuery->where('codigo_rastreo', 'like', "%{$searchTerm}%")
                                     ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                                         $userQuery->where('name', 'like', "%{$searchTerm}%")
                                                  ->orWhere('email', 'like', "%{$searchTerm}%");
                                     });
                      });
                });
            }

            // Aplicar ordenamiento
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $allowedSortFields = ['id', 'monto', 'fecha_pago', 'estado', 'metodo', 'created_at'];
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            }

            // Paginación
            $perPage = min($request->get('per_page', 15), 100);
            $pagos = $query->paginate($perPage);

            Log::info('Listado de pagos obtenido', [
                'total' => $pagos->total(),
                'filtros' => $request->only(['pedido_id', 'estado', 'metodo', 'fecha_desde', 'fecha_hasta'])
            ]);

            return PagoResource::collection($pagos)
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error('Error al obtener listado de pagos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los pagos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePagoRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $datosValidados = $request->validated();
            
            // Verificar que el pedido existe y está en estado válido para pagos
            $pedido = Pedido::findOrFail($datosValidados['pedido_id']);
            
            if (!$this->puedeRecibirPagos($pedido)) {
                return response()->json([
                    'message' => 'El pedido no puede recibir pagos en su estado actual.',
                    'estado_actual' => $pedido->estado
                ], 422);
            }

            // Verificar que no se exceda el monto total del pedido
            $totalPagado = $pedido->pagos()->where('estado', 'pagado')->sum('monto');
            $montoRestante = $pedido->total - $totalPagado;
            
            if ($datosValidados['monto'] > $montoRestante) {
                return response()->json([
                    'message' => 'El monto del pago excede el saldo pendiente del pedido.',
                    'monto_restante' => $montoRestante,
                    'monto_solicitado' => $datosValidados['monto']
                ], 422);
            }

            // Calcular comisión si hay método de pago
            $comision = 0;
            if (isset($datosValidados['metodo_pago_id'])) {
                $metodoPago = \App\Models\MetodoPago::find($datosValidados['metodo_pago_id']);
                if ($metodoPago) {
                    $comision = $metodoPago->calcularComision($datosValidados['monto']);
                }
            }

            // Crear el pago
            $pago = Pago::create([
                'pedido_id' => $datosValidados['pedido_id'],
                'metodo_pago_id' => $datosValidados['metodo_pago_id'] ?? null,
                'monto' => $datosValidados['monto'],
                'comision' => $comision,
                'numero_cuota' => $datosValidados['numero_cuota'] ?? null,
                'fecha_pago' => $datosValidados['fecha_pago'] ?? now(),
                'estado' => $datosValidados['estado'] ?? 'pendiente',
                'metodo' => $datosValidados['metodo'],
                'referencia' => $datosValidados['referencia'] ?? $this->generarReferencia($datosValidados['metodo']),
                'moneda' => $datosValidados['moneda'] ?? $pedido->moneda,
                'respuesta_proveedor' => $datosValidados['respuesta_proveedor'] ?? null,
                'codigo_autorizacion' => $datosValidados['codigo_autorizacion'] ?? null,
                'observaciones' => $datosValidados['observaciones'] ?? null,
            ]);

            // Si es un pago de cuota de crédito, actualizar la cuota correspondiente
            if ($datosValidados['numero_cuota'] && $pedido->tipo_pago === 'credito') {
                $this->actualizarCuotaCredito($pedido, $datosValidados['numero_cuota'], $pago);
            }

            // Si el pago está completado, verificar si el pedido está totalmente pagado
            if ($pago->estado === 'pagado') {
                $this->verificarPedidoCompletamentePagado($pedido);
            }

            DB::commit();

            Log::info("Pago creado exitosamente", [
                'pago_id' => $pago->id,
                'pedido_id' => $pedido->id,
                'monto' => $datosValidados['monto'],
                'metodo' => $datosValidados['metodo']
            ]);

            return (new PagoResource($pago->load(['pedido.user', 'metodoPago'])))
                ->response()
                ->setStatusCode(201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al crear pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al crear el pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Pago $pago): JsonResponse
    {
        try {
            $pago->load(['pedido.user', 'pedido.detalles', 'metodoPago']);
            
            return (new PagoResource($pago))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error('Error al obtener pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener el pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePagoRequest $request, Pago $pago): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $datosValidados = $request->validated();
            $estadoAnterior = $pago->estado;
            
            // Actualizar el pago
            $pago->update($datosValidados);

            // Si cambió el estado a 'pagado', verificar si el pedido está completamente pagado
            if ($estadoAnterior !== 'pagado' && $pago->estado === 'pagado') {
                $this->verificarPedidoCompletamentePagado($pago->pedido);
                
                // Si es una cuota de crédito, actualizar la cuota correspondiente
                if ($pago->numero_cuota && $pago->pedido->tipo_pago === 'credito') {
                    $this->actualizarCuotaCredito($pago->pedido, $pago->numero_cuota, $pago);
                }
            }

            DB::commit();

            Log::info("Pago actualizado exitosamente", [
                'pago_id' => $pago->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $pago->estado
            ]);

            return (new PagoResource($pago->load(['pedido.user', 'metodoPago'])))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al actualizar el pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pago $pago): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            // Verificar que el pago se puede eliminar
            if ($pago->estado === 'pagado') {
                return response()->json([
                    'message' => 'No se puede eliminar un pago que ya está completado.'
                ], 422);
            }

            $pagoId = $pago->id;
            $pedidoId = $pago->pedido_id;
            
            $pago->delete();

            DB::commit();

            Log::info("Pago eliminado exitosamente", [
                'pago_id' => $pagoId,
                'pedido_id' => $pedidoId
            ]);

            return response()->json([
                'message' => 'Pago eliminado exitosamente.'
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar el pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payments by order.
     */
    public function byPedido(Pedido $pedido): JsonResponse
    {
        try {
            $pagos = $pedido->pagos()->with(['pedido.user', 'metodoPago'])->get();
            
            $resumen = [
                'total_pedido' => $pedido->total,
                'total_pagado' => $pagos->where('estado', 'pagado')->sum('monto'),
                'total_pendiente' => $pagos->where('estado', 'pendiente')->sum('monto'),
                'saldo_restante' => $pedido->total - $pagos->where('estado', 'pagado')->sum('monto'),
                'cantidad_pagos' => $pagos->count(),
                'ultimo_pago' => $pagos->sortByDesc('fecha_pago')->first()?->fecha_pago,
            ];

            return response()->json([
                'data' => PagoResource::collection($pagos),
                'resumen' => $resumen
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener pagos por pedido: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los pagos del pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process a payment (mark as completed).
     */
    public function procesarPago(Pago $pago): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            if ($pago->estado === 'pagado') {
                return response()->json([
                    'message' => 'El pago ya está procesado.'
                ], 422);
            }

            $pago->update([
                'estado' => 'pagado',
                'fecha_pago' => now()
            ]);

            // Verificar si el pedido está completamente pagado
            $this->verificarPedidoCompletamentePagado($pago->pedido);

            // Si es una cuota de crédito, actualizar la cuota correspondiente
            if ($pago->numero_cuota && $pago->pedido->tipo_pago === 'credito') {
                $this->actualizarCuotaCredito($pago->pedido, $pago->numero_cuota, $pago);
            }

            DB::commit();

            Log::info("Pago procesado exitosamente", [
                'pago_id' => $pago->id,
                'pedido_id' => $pago->pedido_id
            ]);

            return (new PagoResource($pago->load(['pedido.user', 'metodoPago'])))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al procesar pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al procesar el pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a payment.
     */
    public function cancelarPago(Request $request, Pago $pago): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'motivo' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos de validación incorrectos.',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            if ($pago->estado === 'pagado') {
                return response()->json([
                    'message' => 'No se puede cancelar un pago que ya está completado. Use reembolso en su lugar.'
                ], 422);
            }

            $pago->update([
                'estado' => 'fallido'
            ]);

            DB::commit();

            Log::info("Pago cancelado exitosamente", [
                'pago_id' => $pago->id,
                'motivo' => $request->motivo
            ]);

            return (new PagoResource($pago->load(['pedido.user', 'metodoPago'])))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al cancelar pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al cancelar el pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $fechaDesde = $request->get('fecha_desde', now()->subDays(30)->format('Y-m-d'));
            $fechaHasta = $request->get('fecha_hasta', now()->format('Y-m-d'));

            $query = Pago::whereBetween('created_at', [$fechaDesde, $fechaHasta]);

            $estadisticas = [
                'resumen_general' => [
                    'total_pagos' => $query->count(),
                    'monto_total' => $query->sum('monto'),
                    'pagos_completados' => $query->where('estado', 'pagado')->count(),
                    'pagos_pendientes' => $query->where('estado', 'pendiente')->count(),
                    'pagos_fallidos' => $query->where('estado', 'fallido')->count(),
                    'monto_completado' => $query->where('estado', 'pagado')->sum('monto'),
                    'monto_pendiente' => $query->where('estado', 'pendiente')->sum('monto'),
                ],
                
                'por_metodo' => $query->select('metodo', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(monto) as monto_total'))
                    ->groupBy('metodo')
                    ->orderByDesc('monto_total')
                    ->get(),
                
                'por_estado' => $query->select('estado', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(monto) as monto_total'))
                    ->groupBy('estado')
                    ->get(),
                
                'por_moneda' => $query->select('moneda', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(monto) as monto_total'))
                    ->groupBy('moneda')
                    ->get(),
                
                'tendencia_diaria' => $query->select(
                        DB::raw('DATE(created_at) as fecha'),
                        DB::raw('COUNT(*) as cantidad'),
                        DB::raw('SUM(monto) as monto_total')
                    )
                    ->groupBy(DB::raw('DATE(created_at)'))
                    ->orderBy('fecha')
                    ->get(),
                
                'pagos_mas_altos' => $query->with('pedido.user')
                    ->orderByDesc('monto')
                    ->limit(10)
                    ->get()
                    ->map(function ($pago) {
                        return [
                            'id' => $pago->id,
                            'monto' => $pago->monto,
                            'metodo' => $pago->metodo,
                            'fecha' => $pago->created_at->format('Y-m-d'),
                            'cliente' => $pago->pedido->user->name ?? 'N/A',
                            'pedido_id' => $pago->pedido_id
                        ];
                    }),
            ];

            return response()->json([
                'data' => $estadisticas,
                'periodo' => [
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas de pagos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener las estadísticas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply filters to the query.
     */
    private function aplicarFiltros($query, Request $request): void
    {
        // Filtro por pedido
        if ($request->filled('pedido_id')) {
            $query->where('pedido_id', $request->pedido_id);
        }

        // Filtro por método de pago
        if ($request->filled('metodo_pago_id')) {
            $query->where('metodo_pago_id', $request->metodo_pago_id);
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por método de pago
        if ($request->filled('metodo')) {
            $query->where('metodo', $request->metodo);
        }

        // Filtro por moneda
        if ($request->filled('moneda')) {
            $query->where('moneda', $request->moneda);
        }

        // Filtro por rango de monto
        if ($request->filled('monto_min')) {
            $query->where('monto', '>=', $request->monto_min);
        }
        if ($request->filled('monto_max')) {
            $query->where('monto', '<=', $request->monto_max);
        }

        // Filtro por rango de fechas
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_pago', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_pago', '<=', $request->fecha_hasta);
        }

        // Filtro por número de cuota
        if ($request->filled('numero_cuota')) {
            $query->where('numero_cuota', $request->numero_cuota);
        }

        // Filtro por referencia
        if ($request->filled('referencia')) {
            $query->where('referencia', 'like', '%' . $request->referencia . '%');
        }

        // Filtro por código de autorización
        if ($request->filled('codigo_autorizacion')) {
            $query->where('codigo_autorizacion', 'like', '%' . $request->codigo_autorizacion . '%');
        }

        // Filtro por rango de comisión
        if ($request->filled('comision_min')) {
            $query->where('comision', '>=', $request->comision_min);
        }
        if ($request->filled('comision_max')) {
            $query->where('comision', '<=', $request->comision_max);
        }
    }

    /**
     * Check if an order can receive payments.
     */
    private function puedeRecibirPagos(Pedido $pedido): bool
    {
        return in_array($pedido->estado, ['pendiente', 'aprobado', 'en_proceso', 'enviado']);
    }

    /**
     * Generate a payment reference.
     */
    private function generarReferencia(string $metodo): string
    {
        $prefijo = match ($metodo) {
            'yape' => 'YAPE',
            'plin' => 'PLIN',
            'transferencia' => 'TRANS',
            'tarjeta' => 'CARD',
            'efectivo' => 'CASH',
            'paypal' => 'PAYPAL',
            default => 'PAY'
        };

        return $prefijo . '-' . now()->format('Ymd') . '-' . rand(100000, 999999);
    }

    /**
     * Update credit installment when payment is made.
     */
    private function actualizarCuotaCredito(Pedido $pedido, int $numeroCuota, Pago $pago): void
    {
        $cuota = CuotaCredito::where('pedido_id', $pedido->id)
            ->where('numero_cuota', $numeroCuota)
            ->first();

        if ($cuota) {
            $cuota->update([
                'estado' => 'pagado',
                'fecha_pago' => $pago->fecha_pago
            ]);
        }
    }

    /**
     * Check if order is fully paid and update status.
     */
    private function verificarPedidoCompletamentePagado(Pedido $pedido): void
    {
        $totalPagado = $pedido->pagos()->where('estado', 'pagado')->sum('monto');
        
        if ($totalPagado >= $pedido->total && $pedido->estado === 'pendiente') {
            $pedido->update(['estado' => 'aprobado']);
            
            Log::info("Pedido marcado como aprobado por pago completo", [
                'pedido_id' => $pedido->id,
                'total_pagado' => $totalPagado,
                'total_pedido' => $pedido->total
            ]);
        }
    }

    /**
     * Obtener pagos por método de pago
     */
    public function byMetodoPago(\App\Models\MetodoPago $metodoPago, Request $request): JsonResponse
    {
        try {
            $query = $metodoPago->pagos()->with(['pedido.user']);

            // Aplicar filtros adicionales
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('fecha_desde')) {
                $query->whereDate('fecha_pago', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('fecha_pago', '<=', $request->fecha_hasta);
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $perPage = min($request->get('per_page', 15), 100);
            $pagos = $query->paginate($perPage);

            $resumen = [
                'metodo_pago' => [
                    'id' => $metodoPago->id,
                    'nombre' => $metodoPago->nombre,
                    'tipo' => $metodoPago->tipo,
                ],
                'total_pagos' => $pagos->total(),
                'monto_total' => $metodoPago->pagos()->sum('monto'),
                'comision_total' => $metodoPago->pagos()->sum('comision'),
                'pagos_exitosos' => $metodoPago->pagos()->where('estado', 'pagado')->count(),
                'tasa_exito' => $metodoPago->pagos()->count() > 0 
                    ? round(($metodoPago->pagos()->where('estado', 'pagado')->count() / $metodoPago->pagos()->count()) * 100, 2)
                    : 0
            ];

            return response()->json([
                'data' => PagoResource::collection($pagos),
                'resumen' => $resumen,
                'meta' => [
                    'current_page' => $pagos->currentPage(),
                    'last_page' => $pagos->lastPage(),
                    'per_page' => $pagos->perPage(),
                    'total' => $pagos->total(),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener pagos por método de pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los pagos del método de pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear pago con método de pago específico
     */
    public function crearConMetodoPago(Request $request, \App\Models\MetodoPago $metodoPago): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'pedido_id' => 'required|exists:pedidos,id',
            'monto' => 'required|numeric|min:0.01',
            'numero_cuota' => 'nullable|integer|min:1',
            'observaciones' => 'nullable|string|max:1000',
            'codigo_autorizacion' => 'nullable|string|max:100',
            'respuesta_proveedor' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos de validación incorrectos.',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $pedido = Pedido::findOrFail($request->pedido_id);
            
            if (!$this->puedeRecibirPagos($pedido)) {
                return response()->json([
                    'message' => 'El pedido no puede recibir pagos en su estado actual.',
                    'estado_actual' => $pedido->estado
                ], 422);
            }

            // Verificar disponibilidad del método de pago para el monto
            if (!$metodoPago->estaDisponibleParaMonto($request->monto)) {
                return response()->json([
                    'message' => 'El monto no está dentro de los límites permitidos para este método de pago.',
                    'monto_minimo' => $metodoPago->monto_minimo,
                    'monto_maximo' => $metodoPago->monto_maximo,
                    'monto_solicitado' => $request->monto
                ], 422);
            }

            // Calcular comisión automáticamente
            $comision = $metodoPago->calcularComision($request->monto);

            // Crear el pago
            $pago = Pago::create([
                'pedido_id' => $request->pedido_id,
                'metodo_pago_id' => $metodoPago->id,
                'monto' => $request->monto,
                'comision' => $comision,
                'numero_cuota' => $request->numero_cuota,
                'fecha_pago' => now(),
                'estado' => 'pendiente',
                'metodo' => $metodoPago->slug,
                'referencia' => $this->generarReferencia($metodoPago->slug),
                'moneda' => $pedido->moneda,
                'observaciones' => $request->observaciones,
                'codigo_autorizacion' => $request->codigo_autorizacion,
                'respuesta_proveedor' => $request->respuesta_proveedor,
            ]);

            DB::commit();

            Log::info("Pago creado con método específico", [
                'pago_id' => $pago->id,
                'metodo_pago_id' => $metodoPago->id,
                'metodo_nombre' => $metodoPago->nombre,
                'monto' => $request->monto,
                'comision' => $comision
            ]);

            return (new PagoResource($pago->load(['pedido.user', 'metodoPago'])))
                ->response()
                ->setStatusCode(201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al crear pago con método específico: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al crear el pago.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 