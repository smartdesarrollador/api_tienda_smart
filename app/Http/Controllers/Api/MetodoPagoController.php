<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMetodoPagoRequest;
use App\Http\Requests\UpdateMetodoPagoRequest;
use App\Http\Resources\MetodoPagoResource;
use App\Models\MetodoPago;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetodoPagoController extends Controller
{
    /**
     * Listar todos los métodos de pago con filtros opcionales
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $query = MetodoPago::query();

            // Aplicar filtros
            $this->aplicarFiltros($query, $request);

            // Ordenamiento
            $this->aplicarOrdenamiento($query, $request);

            // Paginación
            $perPage = (int) $request->get('per_page', 15);
            $perPage = min($perPage, 100); // Máximo 100 por página

            $metodosPago = $query->paginate($perPage);

            return MetodoPagoResource::collection($metodosPago);

        } catch (Throwable $e) {
            Log::error('Error al listar métodos de pago: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return MetodoPagoResource::collection(collect([]));
        }
    }

    /**
     * Crear un nuevo método de pago
     *
     * @param StoreMetodoPagoRequest $request
     * @return JsonResponse
     */
    public function store(StoreMetodoPagoRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $metodoPago = MetodoPago::create($request->validated());

            DB::commit();

            Log::info('Método de pago creado exitosamente', [
                'id' => $metodoPago->id,
                'nombre' => $metodoPago->nombre,
                'usuario' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Método de pago creado exitosamente',
                'data' => new MetodoPagoResource($metodoPago),
                'success' => true
            ], 201);

        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error al crear método de pago: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated()
            ]);

            return response()->json([
                'message' => 'Error al crear el método de pago',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
                'success' => false
            ], 500);
        }
    }

    /**
     * Mostrar un método de pago específico
     *
     * @param string $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $query = MetodoPago::query();

            // Cargar relaciones si se solicitan
            if ($request->has('with_relations')) {
                $query->withCount(['pedidos', 'pagos']);
            }

            $metodoPago = $query->findOrFail($id);

            return response()->json([
                'data' => new MetodoPagoResource($metodoPago),
                'success' => true
            ]);

        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Método de pago no encontrado',
                'success' => false
            ], 404);

        } catch (Throwable $e) {
            Log::error('Error al mostrar método de pago: ' . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al obtener el método de pago',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
                'success' => false
            ], 500);
        }
    }

    /**
     * Actualizar un método de pago
     *
     * @param UpdateMetodoPagoRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(UpdateMetodoPagoRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $metodoPago = MetodoPago::findOrFail($id);
            $metodoPago->update($request->validated());

            DB::commit();

            Log::info('Método de pago actualizado exitosamente', [
                'id' => $metodoPago->id,
                'nombre' => $metodoPago->nombre,
                'usuario' => auth()->id(),
                'cambios' => $request->validated()
            ]);

            return response()->json([
                'message' => 'Método de pago actualizado exitosamente',
                'data' => new MetodoPagoResource($metodoPago->fresh()),
                'success' => true
            ]);

        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Método de pago no encontrado',
                'success' => false
            ], 404);

        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error al actualizar método de pago: ' . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated()
            ]);

            return response()->json([
                'message' => 'Error al actualizar el método de pago',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
                'success' => false
            ], 500);
        }
    }

    /**
     * Eliminar un método de pago
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $metodoPago = MetodoPago::findOrFail($id);

            // Verificar si tiene pedidos o pagos asociados
            $tienePedidos = $metodoPago->pedidos()->exists();
            $tienePagos = $metodoPago->pagos()->exists();

            if ($tienePedidos || $tienePagos) {
                return response()->json([
                    'message' => 'No se puede eliminar el método de pago porque tiene transacciones asociadas',
                    'success' => false,
                    'details' => [
                        'pedidos_asociados' => $tienePedidos,
                        'pagos_asociados' => $tienePagos
                    ]
                ], 409);
            }

            $nombreMetodo = $metodoPago->nombre;
            $metodoPago->delete();

            DB::commit();

            Log::info('Método de pago eliminado exitosamente', [
                'id' => $id,
                'nombre' => $nombreMetodo,
                'usuario' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Método de pago eliminado exitosamente',
                'success' => true
            ]);

        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Método de pago no encontrado',
                'success' => false
            ], 404);

        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error al eliminar método de pago: ' . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al eliminar el método de pago',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
                'success' => false
            ], 500);
        }
    }

    /**
     * Obtener métodos de pago activos para formularios de selección
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function forSelect(Request $request): JsonResponse
    {
        try {
            $query = MetodoPago::activo()->ordenados();

            // Filtros adicionales opcionales
            if ($request->filled('tipo')) {
                $query->porTipo($request->string('tipo'));
            }

            if ($request->filled('pais')) {
                $query->disponibleEnPais($request->string('pais'));
            }

            if ($request->filled('moneda')) {
                $query->soportaMoneda($request->string('moneda'));
            }

            if ($request->filled('monto')) {
                $monto = (float) $request->get('monto');
                $query->disponibleParaMonto($monto);
            }

            $metodosPago = $query->select([
                'id',
                'nombre',
                'slug',
                'tipo',
                'logo',
                'icono_clase',
                'color_primario',
                'permite_cuotas',
                'cuotas_maximas',
                'comision_porcentaje',
                'comision_fija',
                'tiempo_procesamiento'
            ])->get();

            return response()->json([
                'data' => $metodosPago->map(function ($metodo) {
                    return [
                        'id' => $metodo->id,
                        'nombre' => $metodo->nombre,
                        'slug' => $metodo->slug,
                        'tipo' => $metodo->tipo,
                        'logo_url' => $metodo->logo_url,
                        'icono_clase' => $metodo->icono_clase,
                        'color_primario' => $metodo->color_primario,
                        'permite_cuotas' => $metodo->permite_cuotas,
                        'cuotas_maximas' => $metodo->cuotas_maximas,
                        'comision_porcentaje' => (float) $metodo->comision_porcentaje,
                        'comision_fija' => (float) $metodo->comision_fija,
                        'tiempo_procesamiento_texto' => $metodo->getTiempoProcesamiento()
                    ];
                }),
                'success' => true
            ]);

        } catch (Throwable $e) {
            Log::error('Error al obtener métodos de pago para selección: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

                return response()->json([
                'message' => 'Error al obtener los métodos de pago',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
                'success' => false
            ], 500);
        }
    }

    /**
     * Calcular comisión para un monto específico
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function calcularComision(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'monto' => 'required|numeric|min:0|decimal:0,2'
            ]);

            $metodoPago = MetodoPago::findOrFail($id);
            $monto = (float) $request->get('monto');

            // Verificar si el método está disponible para este monto
            if (!$metodoPago->estaDisponibleParaMonto($monto)) {
                return response()->json([
                    'message' => 'El monto no está dentro de los límites permitidos para este método de pago',
                    'success' => false,
                    'details' => [
                        'monto_minimo' => $metodoPago->monto_minimo,
                        'monto_maximo' => $metodoPago->monto_maximo,
                        'monto_solicitado' => $monto
                    ]
                ], 400);
            }

            $comision = $metodoPago->calcularComision($monto);
            $montoTotal = $monto + $comision;

            return response()->json([
                'data' => [
                    'monto_original' => $monto,
                    'comision_porcentaje' => (float) $metodoPago->comision_porcentaje,
                    'comision_fija' => (float) $metodoPago->comision_fija,
                    'comision_calculada' => $comision,
                    'monto_total' => $montoTotal,
                    'metodo_pago' => [
                        'id' => $metodoPago->id,
                        'nombre' => $metodoPago->nombre,
                        'tipo' => $metodoPago->tipo
                    ]
                ],
                'success' => true
            ]);

        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Método de pago no encontrado',
                'success' => false
            ], 404);

        } catch (Throwable $e) {
            Log::error('Error al calcular comisión: ' . $e->getMessage(), [
                'id' => $id,
                'monto' => $request->get('monto'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al calcular la comisión',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
                'success' => false
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de uso de métodos de pago
     *
     * @return JsonResponse
     */
    public function estadisticas(): JsonResponse
    {
        try {
            $estadisticas = MetodoPago::withCount(['pedidos', 'pagos'])
                ->with(['pagos' => function ($query) {
                    $query->where('estado', 'pagado');
                }])
                ->get()
                ->map(function ($metodo) {
                    $pagosPagados = $metodo->pagos;
                    $montoTotal = $pagosPagados->sum('monto');
                    $comisionTotal = $pagosPagados->sum('comision');

                    return [
                        'id' => $metodo->id,
                        'nombre' => $metodo->nombre,
                        'tipo' => $metodo->tipo,
                        'activo' => $metodo->activo,
                        'total_pedidos' => $metodo->pedidos_count,
                        'total_pagos' => $metodo->pagos_count,
                        'total_pagos_exitosos' => $pagosPagados->count(),
                        'monto_total_procesado' => (float) $montoTotal,
                        'comision_total_generada' => (float) $comisionTotal,
                        'tasa_exito' => $metodo->pagos_count > 0 
                            ? round(($pagosPagados->count() / $metodo->pagos_count) * 100, 2)
                            : 0
                    ];
                });

            return response()->json([
                'data' => $estadisticas,
                'success' => true
            ]);

        } catch (Throwable $e) {
            Log::error('Error al obtener estadísticas de métodos de pago: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al obtener las estadísticas',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
                'success' => false
            ], 500);
        }
    }

    /**
     * Aplicar filtros a la consulta
     *
     * @param $query
     * @param Request $request
     * @return void
     */
    private function aplicarFiltros($query, Request $request): void
    {
        if ($request->filled('activo')) {
            $activo = filter_var($request->get('activo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activo !== null) {
                $query->where('activo', $activo);
            }
        }

        if ($request->filled('tipo')) {
            $query->porTipo($request->string('tipo'));
        }

        if ($request->filled('proveedor')) {
            $query->where('proveedor', $request->string('proveedor'));
        }

        if ($request->filled('permite_cuotas')) {
            $permiteCuotas = filter_var($request->get('permite_cuotas'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($permiteCuotas !== null) {
                $query->where('permite_cuotas', $permiteCuotas);
            }
        }

        if ($request->filled('pais')) {
            $query->disponibleEnPais($request->string('pais'));
        }

        if ($request->filled('moneda')) {
            $query->soportaMoneda($request->string('moneda'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhere('proveedor', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Aplicar ordenamiento a la consulta
     *
     * @param $query
     * @param Request $request
     * @return void
     */
    private function aplicarOrdenamiento($query, Request $request): void
    {
        $sortBy = $request->get('sort_by', 'orden');
        $sortDirection = $request->get('sort_direction', 'asc');

        // Validar campos de ordenamiento permitidos
        $camposPermitidos = [
            'id', 'nombre', 'tipo', 'orden', 'activo', 'comision_porcentaje', 
            'comision_fija', 'created_at', 'updated_at'
        ];

        if (!in_array($sortBy, $camposPermitidos)) {
            $sortBy = 'orden';
        }

        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        if ($sortBy === 'orden') {
            $query->ordenados();
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }
    }
} 