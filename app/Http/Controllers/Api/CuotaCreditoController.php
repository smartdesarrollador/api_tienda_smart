<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCuotaCreditoRequest;
use App\Http\Requests\UpdateCuotaCreditoRequest;
use App\Http\Resources\CuotaCreditoResource;
use App\Models\CuotaCredito;
use App\Models\Pedido;
use App\Models\Pago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;

class CuotaCreditoController extends Controller
{
    /**
     * Display a listing of the resource with advanced filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CuotaCredito::with(['pedido.usuario']);

            // Aplicar filtros
            $this->aplicarFiltros($query, $request);

            // Aplicar búsqueda
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('numero_cuota', 'like', "%{$searchTerm}%")
                      ->orWhere('monto_cuota', 'like', "%{$searchTerm}%")
                      ->orWhereHas('pedido', function ($pedidoQuery) use ($searchTerm) {
                          $pedidoQuery->where('codigo_rastreo', 'like', "%{$searchTerm}%")
                                     ->orWhereHas('usuario', function ($userQuery) use ($searchTerm) {
                                         $userQuery->where('name', 'like', "%{$searchTerm}%")
                                                  ->orWhere('email', 'like', "%{$searchTerm}%");
                                     });
                      });
                });
            }

            // Aplicar ordenamiento
            $sortField = $request->get('sort_field', 'fecha_vencimiento');
            $sortDirection = $request->get('sort_direction', 'asc');
            
            $allowedSortFields = ['id', 'numero_cuota', 'monto_cuota', 'fecha_vencimiento', 'fecha_pago', 'estado', 'created_at'];
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            }

            // Paginación
            $perPage = min($request->get('per_page', 15), 100);
            $cuotas = $query->paginate($perPage);

            Log::info('Listado de cuotas de crédito obtenido', [
                'total' => $cuotas->total(),
                'filtros' => $request->only(['pedido_id', 'estado', 'fecha_desde', 'fecha_hasta', 'vencidas'])
            ]);

            return CuotaCreditoResource::collection($cuotas)
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error('Error al obtener listado de cuotas de crédito: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener las cuotas de crédito.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCuotaCreditoRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $datosValidados = $request->validated();
            
            // Verificar que el pedido existe y es de tipo crédito
            $pedido = Pedido::findOrFail($datosValidados['pedido_id']);
            
            if ($pedido->tipo_pago !== 'credito') {
                return response()->json([
                    'message' => 'Solo se pueden crear cuotas para pedidos a crédito.',
                    'tipo_pago_actual' => $pedido->tipo_pago
                ], 422);
            }

            // Verificar que no exceda el número total de cuotas del pedido
            if ($datosValidados['numero_cuota'] > $pedido->cuotas) {
                return response()->json([
                    'message' => 'El número de cuota excede el total de cuotas del pedido.',
                    'numero_cuota_solicitado' => $datosValidados['numero_cuota'],
                    'total_cuotas_pedido' => $pedido->cuotas
                ], 422);
            }

            // Verificar que no existe ya una cuota con ese número para el pedido
            $cuotaExistente = CuotaCredito::where('pedido_id', $pedido->id)
                ->where('numero_cuota', $datosValidados['numero_cuota'])
                ->first();

            if ($cuotaExistente) {
                return response()->json([
                    'message' => 'Ya existe una cuota con ese número para este pedido.',
                    'cuota_existente_id' => $cuotaExistente->id
                ], 422);
            }

            // Crear la cuota de crédito
            $cuota = CuotaCredito::create([
                'pedido_id' => $datosValidados['pedido_id'],
                'numero_cuota' => $datosValidados['numero_cuota'],
                'monto_cuota' => $datosValidados['monto_cuota'],
                'interes' => $datosValidados['interes'] ?? 0,
                'mora' => $datosValidados['mora'] ?? 0,
                'fecha_vencimiento' => $datosValidados['fecha_vencimiento'],
                'fecha_pago' => $datosValidados['fecha_pago'] ?? null,
                'estado' => $datosValidados['estado'] ?? 'pendiente',
                'moneda' => $datosValidados['moneda'] ?? $pedido->moneda,
            ]);

            DB::commit();

            Log::info("Cuota de crédito creada exitosamente", [
                'cuota_id' => $cuota->id,
                'pedido_id' => $pedido->id,
                'numero_cuota' => $datosValidados['numero_cuota'],
                'monto_cuota' => $datosValidados['monto_cuota']
            ]);

            return (new CuotaCreditoResource($cuota->load('pedido.usuario')))
                ->response()
                ->setStatusCode(201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al crear cuota de crédito: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al crear la cuota de crédito.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(CuotaCredito $cuotaCredito): JsonResponse
    {
        try {
            $cuotaCredito->load(['pedido.usuario', 'pedido.detalles']);
            
            return (new CuotaCreditoResource($cuotaCredito))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error('Error al obtener cuota de crédito: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener la cuota de crédito.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCuotaCreditoRequest $request, CuotaCredito $cuotaCredito): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $datosValidados = $request->validated();
            $estadoAnterior = $cuotaCredito->estado;
            
            // Actualizar la cuota
            $cuotaCredito->update($datosValidados);

            // Si cambió el estado a 'pagado', registrar fecha de pago automáticamente
            if ($estadoAnterior !== 'pagado' && $cuotaCredito->estado === 'pagado' && !$cuotaCredito->fecha_pago) {
                $cuotaCredito->update(['fecha_pago' => now()]);
            }

            // Si se marcó como atrasada, calcular mora automáticamente
            if ($cuotaCredito->estado === 'atrasado' && !$cuotaCredito->mora) {
                $diasAtraso = Carbon::parse($cuotaCredito->fecha_vencimiento)->diffInDays(now());
                $moraCalculada = $cuotaCredito->monto_cuota * 0.05 * $diasAtraso; // 5% por día
                $cuotaCredito->update(['mora' => $moraCalculada]);
            }

            DB::commit();

            Log::info("Cuota de crédito actualizada exitosamente", [
                'cuota_id' => $cuotaCredito->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $cuotaCredito->estado
            ]);

            return (new CuotaCreditoResource($cuotaCredito->load('pedido.usuario')))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar cuota de crédito: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al actualizar la cuota de crédito.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CuotaCredito $cuotaCredito): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            // Verificar que la cuota se puede eliminar
            if ($cuotaCredito->estado === 'pagado') {
                return response()->json([
                    'message' => 'No se puede eliminar una cuota que ya está pagada.'
                ], 422);
            }

            // Verificar que no hay pagos asociados a esta cuota
            $pagosAsociados = Pago::where('pedido_id', $cuotaCredito->pedido_id)
                ->where('numero_cuota', $cuotaCredito->numero_cuota)
                ->count();

            if ($pagosAsociados > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar una cuota que tiene pagos asociados.',
                    'pagos_asociados' => $pagosAsociados
                ], 422);
            }

            $cuotaId = $cuotaCredito->id;
            $pedidoId = $cuotaCredito->pedido_id;
            $numeroCuota = $cuotaCredito->numero_cuota;
            
            $cuotaCredito->delete();

            DB::commit();

            Log::info("Cuota de crédito eliminada exitosamente", [
                'cuota_id' => $cuotaId,
                'pedido_id' => $pedidoId,
                'numero_cuota' => $numeroCuota
            ]);

            return response()->json([
                'message' => 'Cuota de crédito eliminada exitosamente.'
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar cuota de crédito: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar la cuota de crédito.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get credit installments by order.
     */
    public function byPedido(Pedido $pedido): JsonResponse
    {
        try {
            $cuotas = $pedido->cuotasCredito()->with('pedido.usuario')->get();
            
            $resumen = [
                'total_cuotas' => $cuotas->count(),
                'cuotas_pagadas' => $cuotas->where('estado', 'pagado')->count(),
                'cuotas_pendientes' => $cuotas->where('estado', 'pendiente')->count(),
                'cuotas_atrasadas' => $cuotas->where('estado', 'atrasado')->count(),
                'cuotas_vencidas' => $cuotas->filter(function ($cuota) {
                    return $cuota->fecha_vencimiento < now() && $cuota->estado === 'pendiente';
                })->count(),
                'monto_total_cuotas' => $cuotas->sum('monto_cuota'),
                'monto_pagado' => $cuotas->where('estado', 'pagado')->sum('monto_cuota'),
                'monto_pendiente' => $cuotas->whereIn('estado', ['pendiente', 'atrasado'])->sum('monto_cuota'),
                'monto_mora_total' => $cuotas->sum('mora'),
                'proxima_cuota' => $cuotas->where('estado', 'pendiente')
                    ->sortBy('fecha_vencimiento')
                    ->first()?->fecha_vencimiento,
            ];

            return response()->json([
                'data' => CuotaCreditoResource::collection($cuotas),
                'resumen' => $resumen
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener cuotas por pedido: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener las cuotas del pedido.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overdue installments.
     */
    public function vencidas(Request $request): JsonResponse
    {
        try {
            $query = CuotaCredito::with(['pedido.usuario'])
                ->where('fecha_vencimiento', '<', now())
                ->where('estado', 'pendiente');

            // Filtros adicionales
            if ($request->filled('dias_vencimiento_min')) {
                $fechaLimite = now()->subDays($request->dias_vencimiento_min);
                $query->where('fecha_vencimiento', '<=', $fechaLimite);
            }

            if ($request->filled('monto_min')) {
                $query->where('monto_cuota', '>=', $request->monto_min);
            }

            // Ordenar por fecha de vencimiento (más antiguas primero)
            $query->orderBy('fecha_vencimiento', 'asc');

            $perPage = min($request->get('per_page', 15), 100);
            $cuotasVencidas = $query->paginate($perPage);

            // Calcular estadísticas de cuotas vencidas
            $estadisticas = [
                'total_cuotas_vencidas' => $cuotasVencidas->total(),
                'monto_total_vencido' => $query->sum('monto_cuota'),
                'promedio_dias_vencimiento' => $this->calcularPromedioDiasVencimiento($query->get()),
                'cliente_con_mas_cuotas_vencidas' => $this->obtenerClienteConMasCuotasVencidas($query->get()),
            ];

            return response()->json([
                'data' => CuotaCreditoResource::collection($cuotasVencidas),
                'estadisticas' => $estadisticas,
                'pagination' => [
                    'current_page' => $cuotasVencidas->currentPage(),
                    'last_page' => $cuotasVencidas->lastPage(),
                    'per_page' => $cuotasVencidas->perPage(),
                    'total' => $cuotasVencidas->total(),
                    'from' => $cuotasVencidas->firstItem(),
                    'to' => $cuotasVencidas->lastItem(),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener cuotas vencidas: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener las cuotas vencidas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark installment as paid.
     */
    public function marcarPagada(CuotaCredito $cuotaCredito): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            if ($cuotaCredito->estado === 'pagado') {
                return response()->json([
                    'message' => 'La cuota ya está marcada como pagada.'
                ], 422);
            }

            $cuotaCredito->update([
                'estado' => 'pagado',
                'fecha_pago' => now()
            ]);

            DB::commit();

            Log::info("Cuota marcada como pagada exitosamente", [
                'cuota_id' => $cuotaCredito->id,
                'pedido_id' => $cuotaCredito->pedido_id,
                'numero_cuota' => $cuotaCredito->numero_cuota
            ]);

            return (new CuotaCreditoResource($cuotaCredito->load('pedido.usuario')))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al marcar cuota como pagada: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al marcar la cuota como pagada.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate late fees for overdue installments.
     */
    public function calcularMora(CuotaCredito $cuotaCredito): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            if ($cuotaCredito->estado === 'pagado') {
                return response()->json([
                    'message' => 'No se puede calcular mora para una cuota ya pagada.'
                ], 422);
            }

            if ($cuotaCredito->fecha_vencimiento >= now()) {
                return response()->json([
                    'message' => 'La cuota aún no está vencida.',
                    'fecha_vencimiento' => $cuotaCredito->fecha_vencimiento->format('Y-m-d')
                ], 422);
            }

            $diasAtraso = Carbon::parse($cuotaCredito->fecha_vencimiento)->diffInDays(now());
            $tasaMoraDiaria = 0.05; // 5% por día
            $moraCalculada = $cuotaCredito->monto_cuota * $tasaMoraDiaria * $diasAtraso;

            $cuotaCredito->update([
                'mora' => $moraCalculada,
                'estado' => 'atrasado'
            ]);

            DB::commit();

            Log::info("Mora calculada exitosamente", [
                'cuota_id' => $cuotaCredito->id,
                'dias_atraso' => $diasAtraso,
                'mora_calculada' => $moraCalculada
            ]);

            return response()->json([
                'data' => new CuotaCreditoResource($cuotaCredito->load('pedido.usuario')),
                'calculo_mora' => [
                    'dias_atraso' => $diasAtraso,
                    'tasa_mora_diaria' => $tasaMoraDiaria,
                    'monto_base' => $cuotaCredito->monto_cuota,
                    'mora_calculada' => $moraCalculada,
                    'monto_total' => $cuotaCredito->monto_cuota + $moraCalculada
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al calcular mora: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al calcular la mora.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get credit installments statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $fechaDesde = $request->get('fecha_desde', now()->subDays(30)->format('Y-m-d'));
            $fechaHasta = $request->get('fecha_hasta', now()->format('Y-m-d'));

            $query = CuotaCredito::whereBetween('created_at', [$fechaDesde, $fechaHasta]);

            $estadisticas = [
                'resumen_general' => [
                    'total_cuotas' => $query->count(),
                    'monto_total' => $query->sum('monto_cuota'),
                    'cuotas_pagadas' => $query->where('estado', 'pagado')->count(),
                    'cuotas_pendientes' => $query->where('estado', 'pendiente')->count(),
                    'cuotas_atrasadas' => $query->where('estado', 'atrasado')->count(),
                    'cuotas_condonadas' => $query->where('estado', 'condonado')->count(),
                    'monto_pagado' => $query->where('estado', 'pagado')->sum('monto_cuota'),
                    'monto_pendiente' => $query->whereIn('estado', ['pendiente', 'atrasado'])->sum('monto_cuota'),
                    'monto_mora_total' => $query->sum('mora'),
                ],
                
                'por_estado' => $query->select('estado', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(monto_cuota) as monto_total'))
                    ->groupBy('estado')
                    ->get(),
                
                'por_moneda' => $query->select('moneda', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(monto_cuota) as monto_total'))
                    ->groupBy('moneda')
                    ->get(),
                
                'vencimientos_proximos' => CuotaCredito::with('pedido.usuario')
                    ->where('estado', 'pendiente')
                    ->whereBetween('fecha_vencimiento', [now(), now()->addDays(30)])
                    ->orderBy('fecha_vencimiento')
                    ->limit(10)
                    ->get()
                    ->map(function ($cuota) {
                        return [
                            'id' => $cuota->id,
                            'numero_cuota' => $cuota->numero_cuota,
                            'monto_cuota' => $cuota->monto_cuota,
                            'fecha_vencimiento' => $cuota->fecha_vencimiento->format('Y-m-d'),
                            'dias_restantes' => $cuota->fecha_vencimiento->diffInDays(now()),
                            'cliente' => $cuota->pedido->usuario->name ?? 'N/A',
                            'pedido_id' => $cuota->pedido_id
                        ];
                    }),
                
                'cuotas_mas_altas' => $query->with('pedido.usuario')
                    ->orderByDesc('monto_cuota')
                    ->limit(10)
                    ->get()
                    ->map(function ($cuota) {
                        return [
                            'id' => $cuota->id,
                            'numero_cuota' => $cuota->numero_cuota,
                            'monto_cuota' => $cuota->monto_cuota,
                            'estado' => $cuota->estado,
                            'fecha_vencimiento' => $cuota->fecha_vencimiento->format('Y-m-d'),
                            'cliente' => $cuota->pedido->usuario->name ?? 'N/A',
                            'pedido_id' => $cuota->pedido_id
                        ];
                    }),
                
                'tendencia_mensual' => $query->select(
                        DB::raw('YEAR(fecha_vencimiento) as año'),
                        DB::raw('MONTH(fecha_vencimiento) as mes'),
                        DB::raw('COUNT(*) as cantidad'),
                        DB::raw('SUM(monto_cuota) as monto_total')
                    )
                    ->groupBy(DB::raw('YEAR(fecha_vencimiento)'), DB::raw('MONTH(fecha_vencimiento)'))
                    ->orderBy('año')
                    ->orderBy('mes')
                    ->get(),
            ];

            return response()->json([
                'data' => $estadisticas,
                'periodo' => [
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas de cuotas de crédito: ' . $e->getMessage());
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

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por moneda
        if ($request->filled('moneda')) {
            $query->where('moneda', $request->moneda);
        }

        // Filtro por rango de monto
        if ($request->filled('monto_min')) {
            $query->where('monto_cuota', '>=', $request->monto_min);
        }
        if ($request->filled('monto_max')) {
            $query->where('monto_cuota', '<=', $request->monto_max);
        }

        // Filtro por rango de fechas de vencimiento
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_vencimiento', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_vencimiento', '<=', $request->fecha_hasta);
        }

        // Filtro por número de cuota
        if ($request->filled('numero_cuota')) {
            $query->where('numero_cuota', $request->numero_cuota);
        }

        // Filtro para cuotas vencidas
        if ($request->filled('vencidas') && $request->vencidas) {
            $query->where('fecha_vencimiento', '<', now())
                  ->where('estado', 'pendiente');
        }

        // Filtro para cuotas próximas a vencer
        if ($request->filled('proximas_vencer')) {
            $diasLimite = $request->get('dias_limite', 7);
            $query->where('fecha_vencimiento', '>=', now())
                  ->where('fecha_vencimiento', '<=', now()->addDays($diasLimite))
                  ->where('estado', 'pendiente');
        }
    }

    /**
     * Calculate average days overdue.
     */
    private function calcularPromedioDiasVencimiento($cuotas): float
    {
        if ($cuotas->isEmpty()) {
            return 0;
        }

        $totalDias = $cuotas->sum(function ($cuota) {
            return Carbon::parse($cuota->fecha_vencimiento)->diffInDays(now());
        });

        return round($totalDias / $cuotas->count(), 2);
    }

    /**
     * Get client with most overdue installments.
     */
    private function obtenerClienteConMasCuotasVencidas($cuotas): ?array
    {
        $clientesCuotas = $cuotas->groupBy('pedido.usuario.id');
        
        if ($clientesCuotas->isEmpty()) {
            return null;
        }

        $clienteConMasCuotas = $clientesCuotas->map(function ($cuotasCliente) {
            $primeraCuota = $cuotasCliente->first();
            return [
                'cliente_id' => $primeraCuota->pedido->usuario->id,
                'cliente_nombre' => $primeraCuota->pedido->usuario->name,
                'cliente_email' => $primeraCuota->pedido->usuario->email,
                'cantidad_cuotas_vencidas' => $cuotasCliente->count(),
                'monto_total_vencido' => $cuotasCliente->sum('monto_cuota')
            ];
        })->sortByDesc('cantidad_cuotas_vencidas')->first();

        return $clienteConMasCuotas;
    }
} 