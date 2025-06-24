<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDatosFacturacionRequest;
use App\Http\Requests\UpdateDatosFacturacionRequest;
use App\Http\Resources\DatosFacturacionResource;
use App\Models\DatosFacturacion;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DatosFacturacionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = DatosFacturacion::query();

            // Aplicar filtros
            $this->applyFilters($query, $request);

            // Aplicar búsqueda
            if ($request->filled('buscar')) {
                $this->applySearch($query, $request->input('buscar'));
            }

            // Aplicar ordenamiento
            $this->applyOrdering($query, $request);

            // Cargar relaciones por defecto
            $query->with(['cliente:id,nombre_completo,dni,telefono,estado']);

            // Determinar el tipo de response
            $perPage = min($request->input('per_page', 15), 100);
            
            if ($request->boolean('sin_paginacion')) {
                // Sin paginación (para exportes, etc.)
                $datos = $query->limit(1000)->get();
                return response()->json([
                    'data' => DatosFacturacionResource::collection($datos),
                    'meta' => [
                        'total' => $datos->count(),
                        'filtros_aplicados' => $this->getAppliedFilters($request),
                    ]
                ]);
            }

            // Respuesta paginada
            $datos = $query->paginate($perPage);
            
            return response()->json([
                'data' => DatosFacturacionResource::collection($datos->items()),
                'meta' => [
                    'current_page' => $datos->currentPage(),
                    'last_page' => $datos->lastPage(),
                    'per_page' => $datos->perPage(),
                    'total' => $datos->total(),
                    'filtros_aplicados' => $this->getAppliedFilters($request),
                ],
                'links' => [
                    'first' => $datos->url(1),
                    'last' => $datos->url($datos->lastPage()),
                    'prev' => $datos->previousPageUrl(),
                    'next' => $datos->nextPageUrl(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener datos de facturación: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener la lista de datos de facturación',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDatosFacturacionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosFacturacion = DatosFacturacion::create($request->validated());

            // Si se establece como predeterminado, quitar el predeterminado de otros
            if ($datosFacturacion->predeterminado) {
                $datosFacturacion->establecerComoPredeterminado();
            }

            // Cargar relaciones para la respuesta
            $datosFacturacion->load(['cliente:id,nombre_completo,dni,telefono,estado']);

            DB::commit();

            Log::info('Datos de facturación creados exitosamente', [
                'datos_facturacion_id' => $datosFacturacion->id,
                'cliente_id' => $datosFacturacion->cliente_id,
                'tipo_documento' => $datosFacturacion->tipo_documento
            ]);

            return response()->json([
                'message' => 'Datos de facturación creados exitosamente',
                'data' => new DatosFacturacionResource($datosFacturacion)
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear datos de facturación: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al crear los datos de facturación',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $query = DatosFacturacion::where('id', $id);

            // Cargar relaciones
            $query->with(['cliente']);

            $datosFacturacion = $query->firstOrFail();

            return response()->json([
                'data' => new DatosFacturacionResource($datosFacturacion)
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Datos de facturación no encontrados'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error al obtener datos de facturación: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener los datos de facturación',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDatosFacturacionRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosFacturacion = DatosFacturacion::findOrFail($id);
            $datosFacturacion->update($request->validated());

            // Si se establece como predeterminado, quitar el predeterminado de otros
            if ($request->input('predeterminado') === true) {
                $datosFacturacion->establecerComoPredeterminado();
            }

            // Recargar relaciones para la respuesta
            $datosFacturacion->refresh();
            $datosFacturacion->load(['cliente:id,nombre_completo,dni,telefono,estado']);

            DB::commit();

            Log::info('Datos de facturación actualizados exitosamente', [
                'datos_facturacion_id' => $datosFacturacion->id,
                'cliente_id' => $datosFacturacion->cliente_id
            ]);

            return response()->json([
                'message' => 'Datos de facturación actualizados exitosamente',
                'data' => new DatosFacturacionResource($datosFacturacion)
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Datos de facturación no encontrados'
            ], 404);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar datos de facturación: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al actualizar los datos de facturación',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosFacturacion = DatosFacturacion::findOrFail($id);
            
            // Verificar si es el único dato de facturación del cliente
            $clienteId = $datosFacturacion->cliente_id;
            $totalDatos = DatosFacturacion::where('cliente_id', $clienteId)->count();
            
            if ($totalDatos === 1) {
                return response()->json([
                    'message' => 'No se puede eliminar el único dato de facturación del cliente.'
                ], 422);
            }

            // Si era el predeterminado, establecer otro como predeterminado
            $eraPredeterminado = $datosFacturacion->predeterminado;
            
            $datosFacturacion->delete();

            if ($eraPredeterminado) {
                $nuevosPredeterminados = DatosFacturacion::where('cliente_id', $clienteId)
                    ->where('activo', true)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($nuevosPredeterminados) {
                    $nuevosPredeterminados->establecerComoPredeterminado();
                }
            }

            DB::commit();

            Log::info('Datos de facturación eliminados exitosamente', [
                'datos_facturacion_id' => $id,
                'cliente_id' => $clienteId
            ]);

            return response()->json([
                'message' => 'Datos de facturación eliminados exitosamente'
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Datos de facturación no encontrados'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar datos de facturación: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al eliminar los datos de facturación',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener datos de facturación por cliente
     */
    public function byCliente(Request $request, string $clienteId): JsonResponse
    {
        try {
            $cliente = Cliente::findOrFail($clienteId);
            
            $query = DatosFacturacion::where('cliente_id', $clienteId);
            
            // Aplicar filtros específicos
            if ($request->boolean('solo_activos')) {
                $query->where('activo', true);
            }
            
            if ($request->boolean('solo_predeterminado')) {
                $query->where('predeterminado', true);
            }
            
            if ($request->filled('tipo_documento')) {
                $query->where('tipo_documento', $request->input('tipo_documento'));
            }

            $datos = $query->orderByDesc('predeterminado')
                          ->orderByDesc('created_at')
                          ->get();

            return response()->json([
                'data' => DatosFacturacionResource::collection($datos),
                'cliente' => [
                    'id' => $cliente->id,
                    'nombre_completo' => $cliente->nombre_completo_formateado,
                    'dni' => $cliente->dni,
                ],
                'meta' => [
                    'total' => $datos->count(),
                    'activos' => $datos->where('activo', true)->count(),
                    'predeterminado' => $datos->where('predeterminado', true)->first()?->id,
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error al obtener datos de facturación por cliente: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener los datos de facturación del cliente',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Establecer como predeterminado
     */
    public function establecerPredeterminado(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosFacturacion = DatosFacturacion::findOrFail($id);
            
            // Verificar que esté activo
            if (!$datosFacturacion->activo) {
                return response()->json([
                    'message' => 'Solo se pueden establecer como predeterminados los datos activos.'
                ], 422);
            }

            $datosFacturacion->establecerComoPredeterminado();

            DB::commit();

            Log::info('Datos de facturación establecidos como predeterminados', [
                'datos_facturacion_id' => $id,
                'cliente_id' => $datosFacturacion->cliente_id
            ]);

            return response()->json([
                'message' => 'Datos de facturación establecidos como predeterminados exitosamente',
                'data' => new DatosFacturacionResource($datosFacturacion)
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Datos de facturación no encontrados'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al establecer predeterminado: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al establecer como predeterminado',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Activar datos de facturación
     */
    public function activar(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosFacturacion = DatosFacturacion::findOrFail($id);
            $datosFacturacion->activar();

            DB::commit();

            Log::info('Datos de facturación activados', ['datos_facturacion_id' => $id]);

            return response()->json([
                'message' => 'Datos de facturación activados exitosamente',
                'data' => new DatosFacturacionResource($datosFacturacion)
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Datos de facturación no encontrados'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al activar datos de facturación: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al activar los datos de facturación',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Desactivar datos de facturación
     */
    public function desactivar(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosFacturacion = DatosFacturacion::findOrFail($id);
            
            // Verificar que no sea el único activo del cliente
            $activosDelCliente = DatosFacturacion::where('cliente_id', $datosFacturacion->cliente_id)
                ->where('activo', true)
                ->count();
            
            if ($activosDelCliente === 1) {
                return response()->json([
                    'message' => 'No se puede desactivar el único dato de facturación activo del cliente.'
                ], 422);
            }

            $datosFacturacion->desactivar();

            DB::commit();

            Log::info('Datos de facturación desactivados', ['datos_facturacion_id' => $id]);

            return response()->json([
                'message' => 'Datos de facturación desactivados exitosamente',
                'data' => new DatosFacturacionResource($datosFacturacion)
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Datos de facturación no encontrados'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al desactivar datos de facturación: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al desactivar los datos de facturación',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Validar documento
     */
    public function validarDocumento(Request $request): JsonResponse
    {
        $request->validate([
            'tipo_documento' => 'required|in:' . implode(',', DatosFacturacion::TIPOS_DOCUMENTO),
            'numero_documento' => 'required|string|max:20'
        ]);

        try {
            $tipoDocumento = $request->input('tipo_documento');
            $numeroDocumento = preg_replace('/[^0-9A-Za-z]/', '', $request->input('numero_documento'));
            
            // Crear instancia temporal para validación
            $temp = new DatosFacturacion([
                'tipo_documento' => $tipoDocumento,
                'numero_documento' => $numeroDocumento,
            ]);

            $esValido = $temp->validarNumeroDocumento();
            
            return response()->json([
                'valido' => $esValido,
                'numero_documento_limpio' => $numeroDocumento,
                'tipo_documento' => $tipoDocumento,
                'formato_esperado' => $this->getFormatoEsperado($tipoDocumento),
            ]);

        } catch (\Exception $e) {
            Log::error('Error al validar documento: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al validar el documento',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de datos de facturación
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total' => DatosFacturacion::count(),
                'activos' => DatosFacturacion::activos()->count(),
                'predeterminados' => DatosFacturacion::predeterminados()->count(),
                'por_tipo_documento' => DatosFacturacion::selectRaw('tipo_documento, COUNT(*) as total')
                    ->groupBy('tipo_documento')
                    ->pluck('total', 'tipo_documento'),
                'por_estado' => [
                    'activos' => DatosFacturacion::where('activo', true)->count(),
                    'inactivos' => DatosFacturacion::where('activo', false)->count(),
                    'predeterminados' => DatosFacturacion::where('predeterminado', true)->count(),
                ],
                'clientes_con_datos' => DatosFacturacion::distinct('cliente_id')->count(),
                'promedio_por_cliente' => round(DatosFacturacion::count() / max(DatosFacturacion::distinct('cliente_id')->count(), 1), 2),
                'documentos_validos' => $this->getValidDocumentsCount(),
                'nuevos_ultimo_mes' => DatosFacturacion::where('created_at', '>=', now()->subMonth())->count(),
            ];

            return response()->json([
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de datos de facturación: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener estadísticas',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Aplicar filtros a la consulta
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->input('cliente_id'));
        }

        if ($request->filled('tipo_documento')) {
            $query->where('tipo_documento', $request->input('tipo_documento'));
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('predeterminado')) {
            $query->where('predeterminado', $request->boolean('predeterminado'));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
        }

        if ($request->filled('departamento_fiscal')) {
            $query->where('departamento_fiscal', 'like', '%' . $request->input('departamento_fiscal') . '%');
        }

        if ($request->filled('provincia_fiscal')) {
            $query->where('provincia_fiscal', 'like', '%' . $request->input('provincia_fiscal') . '%');
        }
    }

    /**
     * Aplicar búsqueda a la consulta
     */
    private function applySearch($query, string $search): void
    {
        $search = '%' . $search . '%';
        
        $query->where(function ($query) use ($search) {
            $query->where('numero_documento', 'like', $search)
                  ->orWhere('nombre_facturacion', 'like', $search)
                  ->orWhere('razon_social', 'like', $search)
                  ->orWhere('direccion_fiscal', 'like', $search)
                  ->orWhere('email_facturacion', 'like', $search)
                  ->orWhereHas('cliente', function ($query) use ($search) {
                      $query->where('nombre_completo', 'like', $search)
                            ->orWhere('dni', 'like', $search);
                  });
        });
    }

    /**
     * Aplicar ordenamiento a la consulta
     */
    private function applyOrdering($query, Request $request): void
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $allowedSorts = [
            'id', 'created_at', 'updated_at', 'tipo_documento', 
            'numero_documento', 'nombre_facturacion', 'predeterminado', 'activo'
        ];

        if (in_array($sortBy, $allowedSorts)) {
            if ($sortBy === 'predeterminado') {
                $query->orderByDesc('predeterminado')->orderBy('created_at', 'desc');
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }
        } else {
            $query->orderByDesc('predeterminado')->orderBy('created_at', 'desc');
        }
    }

    /**
     * Obtener filtros aplicados
     */
    private function getAppliedFilters(Request $request): array
    {
        $filters = [];
        
        if ($request->filled('cliente_id')) $filters['cliente_id'] = $request->input('cliente_id');
        if ($request->filled('tipo_documento')) $filters['tipo_documento'] = $request->input('tipo_documento');
        if ($request->has('activo')) $filters['activo'] = $request->boolean('activo');
        if ($request->has('predeterminado')) $filters['predeterminado'] = $request->boolean('predeterminado');
        if ($request->filled('buscar')) $filters['buscar'] = $request->input('buscar');
        
        return $filters;
    }

    /**
     * Obtener formato esperado según tipo de documento
     */
    private function getFormatoEsperado(string $tipoDocumento): string
    {
        return match ($tipoDocumento) {
            DatosFacturacion::TIPO_DNI => '8 dígitos numéricos',
            DatosFacturacion::TIPO_RUC => '11 dígitos numéricos con dígito verificador',
            DatosFacturacion::TIPO_CARNET_EXTRANJERIA => '9 dígitos numéricos',
            DatosFacturacion::TIPO_PASAPORTE => '6-12 caracteres alfanuméricos',
            default => 'Formato variable'
        };
    }

    /**
     * Contar documentos válidos
     */
    private function getValidDocumentsCount(): int
    {
        $count = 0;
        $datos = DatosFacturacion::select('tipo_documento', 'numero_documento')->get();
        
        foreach ($datos as $dato) {
            if ($dato->validarNumeroDocumento()) {
                $count++;
            }
        }
        
        return $count;
    }
}
