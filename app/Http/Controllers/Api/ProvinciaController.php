<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProvinciaResource;
use App\Models\Provincia;
use App\Models\Departamento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Exception;

class ProvinciaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Provincia::query();

            // Cargar relaciones condicionalmente
            $with = [];
            if ($request->boolean('with_departamento', false)) {
                $with[] = 'departamento';
            }
            
            if ($request->boolean('with_distritos', false)) {
                $with[] = 'distritos';
            }
            
            if (!empty($with)) {
                $query->with($with);
            }

            // Filtros
            if ($request->filled('departamento_id')) {
                $query->where('departamento_id', $request->departamento_id);
            }

            if ($request->filled('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }

            if ($request->filled('codigo')) {
                $query->where('codigo', 'like', '%' . $request->codigo . '%');
            }

            if ($request->filled('codigo_inei')) {
                $query->where('codigo_inei', $request->codigo_inei);
            }

            // Filtro por país a través del departamento
            if ($request->filled('pais')) {
                $query->whereHas('departamento', function ($q) use ($request) {
                    $q->where('pais', $request->pais);
                });
            }

            // Búsqueda por texto
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%")
                      ->orWhere('codigo_inei', 'like', "%{$search}%")
                      ->orWhereHas('departamento', function ($deptQuery) use ($search) {
                          $deptQuery->where('nombre', 'like', "%{$search}%");
                      });
                });
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'nombre');
            $sortDirection = $request->get('sort_direction', 'asc');
            
            $allowedSorts = ['nombre', 'codigo', 'codigo_inei', 'activo', 'created_at', 'updated_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Paginación o listado completo
            if ($request->boolean('paginate', true)) {
                $perPage = min($request->get('per_page', 15), 100);
                $provincias = $query->paginate($perPage);

                return response()->json([
                    'data' => ProvinciaResource::collection($provincias->items()),
                    'meta' => [
                        'current_page' => $provincias->currentPage(),
                        'last_page' => $provincias->lastPage(),
                        'per_page' => $provincias->perPage(),
                        'total' => $provincias->total(),
                        'from' => $provincias->firstItem(),
                        'to' => $provincias->lastItem(),
                    ],
                    'links' => [
                        'first' => $provincias->url(1),
                        'last' => $provincias->url($provincias->lastPage()),
                        'prev' => $provincias->previousPageUrl(),
                        'next' => $provincias->nextPageUrl(),
                    ]
                ]);
            } else {
                $provincias = $query->get();
                return response()->json([
                    'data' => ProvinciaResource::collection($provincias),
                    'meta' => [
                        'total' => $provincias->count(),
                    ]
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error al obtener provincias: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener las provincias.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'departamento_id' => 'required|exists:departamentos,id',
            'nombre' => 'required|string|max:100',
            'codigo' => 'required|string|max:10|unique:provincias,codigo',
            'codigo_inei' => 'nullable|string|max:10|unique:provincias,codigo_inei',
            'activo' => 'boolean',
        ], [
            'departamento_id.required' => 'El departamento es obligatorio.',
            'departamento_id.exists' => 'El departamento seleccionado no existe.',
            'nombre.required' => 'El nombre de la provincia es obligatorio.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'codigo.required' => 'El código de la provincia es obligatorio.',
            'codigo.unique' => 'Ya existe una provincia con este código.',
            'codigo_inei.unique' => 'Ya existe una provincia con este código INEI.',
        ]);

        try {
            // Verificar que el departamento esté activo
            $departamento = Departamento::findOrFail($request->departamento_id);
            if (!$departamento->activo) {
                return response()->json([
                    'message' => 'No se puede crear una provincia en un departamento inactivo.',
                    'departamento' => $departamento->nombre
                ], 422);
            }

            $provincia = Provincia::create([
                'departamento_id' => $request->departamento_id,
                'nombre' => $request->nombre,
                'codigo' => strtoupper($request->codigo),
                'codigo_inei' => $request->codigo_inei,
                'activo' => $request->boolean('activo', true),
            ]);

            Log::info("Provincia creada exitosamente", [
                'provincia_id' => $provincia->id,
                'nombre' => $provincia->nombre,
                'codigo' => $provincia->codigo,
                'departamento_id' => $provincia->departamento_id,
            ]);

            return (new ProvinciaResource($provincia->load('departamento')))
                ->response()
                ->setStatusCode(201);

        } catch (Exception $e) {
            Log::error('Error al crear provincia: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al crear la provincia.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Provincia $provincia): JsonResponse
    {
        try {
            // Cargar relaciones condicionalmente
            $with = [];
            if ($request->boolean('with_departamento', false)) {
                $with[] = 'departamento';
            }
            
            if ($request->boolean('with_distritos', false)) {
                $with[] = 'distritos';
            }
            
            if (!empty($with)) {
                $provincia->load($with);
            }

            return (new ProvinciaResource($provincia))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error("Error al obtener provincia ID {$provincia->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener la provincia.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Provincia $provincia): JsonResponse
    {
        $request->validate([
            'departamento_id' => 'sometimes|required|exists:departamentos,id',
            'nombre' => 'sometimes|required|string|max:100',
            'codigo' => [
                'sometimes', 
                'required', 
                'string', 
                'max:10',
                Rule::unique('provincias', 'codigo')->ignore($provincia->id)
            ],
            'codigo_inei' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('provincias', 'codigo_inei')->ignore($provincia->id)
            ],
            'activo' => 'boolean',
        ], [
            'departamento_id.exists' => 'El departamento seleccionado no existe.',
            'nombre.required' => 'El nombre de la provincia es obligatorio.',
            'codigo.required' => 'El código de la provincia es obligatorio.',
            'codigo.unique' => 'Ya existe una provincia con este código.',
            'codigo_inei.unique' => 'Ya existe una provincia con este código INEI.',
        ]);

        try {
            $datosActualizacion = $request->only(['departamento_id', 'nombre', 'codigo', 'codigo_inei', 'activo']);
            
            // Verificar que el nuevo departamento esté activo si se está cambiando
            if (isset($datosActualizacion['departamento_id']) && $datosActualizacion['departamento_id'] !== $provincia->departamento_id) {
                $nuevoDepartamento = Departamento::findOrFail($datosActualizacion['departamento_id']);
                if (!$nuevoDepartamento->activo) {
                    return response()->json([
                        'message' => 'No se puede mover la provincia a un departamento inactivo.',
                        'departamento' => $nuevoDepartamento->nombre
                    ], 422);
                }
            }
            
            if (isset($datosActualizacion['codigo'])) {
                $datosActualizacion['codigo'] = strtoupper($datosActualizacion['codigo']);
            }

            $provincia->update($datosActualizacion);

            Log::info("Provincia actualizada exitosamente", [
                'provincia_id' => $provincia->id,
                'nombre' => $provincia->nombre,
                'cambios' => array_keys($datosActualizacion)
            ]);

            return (new ProvinciaResource($provincia->load('departamento')))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error("Error al actualizar provincia ID {$provincia->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al actualizar la provincia.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Provincia $provincia): JsonResponse
    {
        try {
            // Verificar si tiene distritos asociados
            if ($provincia->distritos()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar la provincia porque tiene distritos asociados.',
                    'distritos_count' => $provincia->distritos()->count()
                ], 422);
            }

            $provinciaId = $provincia->id;
            $provinciaNombre = $provincia->nombre;
            
            $provincia->delete();

            Log::info("Provincia eliminada exitosamente", [
                'provincia_id' => $provinciaId,
                'nombre' => $provinciaNombre
            ]);

            return response()->json(null, 204);

        } catch (Exception $e) {
            Log::error("Error al eliminar provincia ID {$provincia->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar la provincia.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle status de la provincia
     */
    public function toggleStatus(Provincia $provincia): JsonResponse
    {
        try {
            $nuevoEstado = !$provincia->activo;
            
            // Si se activa la provincia, verificar que el departamento esté activo
            if ($nuevoEstado && !$provincia->departamento->activo) {
                return response()->json([
                    'message' => 'No se puede activar la provincia porque el departamento está inactivo.',
                    'departamento' => $provincia->departamento->nombre
                ], 422);
            }
            
            $provincia->update(['activo' => $nuevoEstado]);

            // Si se desactiva la provincia, desactivar también sus distritos
            if (!$nuevoEstado) {
                $provincia->distritos()->update(['activo' => false]);
            }

            Log::info("Estado de la provincia cambiado", [
                'provincia_id' => $provincia->id,
                'nombre' => $provincia->nombre,
                'estado_anterior' => !$nuevoEstado,
                'nuevo_estado' => $nuevoEstado
            ]);

            return (new ProvinciaResource($provincia->load('departamento')))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error("Error al cambiar estado de la provincia ID {$provincia->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al cambiar el estado de la provincia.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener provincias por departamento
     */
    public function porDepartamento(Request $request, Departamento $departamento): JsonResponse
    {
        $request->validate([
            'activo' => 'boolean',
            'with_distritos' => 'boolean',
        ]);

        try {
            $query = $departamento->provincias();

            if ($request->filled('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }

            // Cargar distritos si se solicita
            if ($request->boolean('with_distritos', false)) {
                $query->with('distritos');
            }

            $provincias = $query->orderBy('nombre')->get();

            return response()->json([
                'data' => ProvinciaResource::collection($provincias),
                'departamento' => [
                    'id' => $departamento->id,
                    'nombre' => $departamento->nombre,
                    'codigo' => $departamento->codigo,
                    'activo' => $departamento->activo,
                ],
                'total' => $provincias->count(),
                'activas' => $provincias->where('activo', true)->count(),
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener provincias por departamento: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener las provincias del departamento.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de provincias
     */
    public function estadisticas(Request $request): JsonResponse
    {
        try {
            $query = Provincia::query();
            
            // Filtro por departamento si se especifica
            if ($request->filled('departamento_id')) {
                $query->where('departamento_id', $request->departamento_id);
            }

            $estadisticas = [
                'total_provincias' => (clone $query)->count(),
                'provincias_activas' => (clone $query)->where('activo', true)->count(),
                'provincias_inactivas' => (clone $query)->where('activo', false)->count(),
                'por_departamento' => (clone $query)
                    ->with('departamento:id,nombre')
                    ->select('departamento_id', DB::raw('count(*) as total'))
                    ->groupBy('departamento_id')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'departamento_id' => $item->departamento_id,
                            'departamento_nombre' => $item->departamento->nombre,
                            'total_provincias' => $item->total,
                        ];
                    }),
                'con_distritos' => (clone $query)->has('distritos')->count(),
                'sin_distritos' => (clone $query)->doesntHave('distritos')->count(),
                'total_distritos' => DB::table('distritos')
                    ->when($request->filled('departamento_id'), function ($q) use ($request) {
                        $q->whereIn('provincia_id', function ($subQuery) use ($request) {
                            $subQuery->select('id')->from('provincias')->where('departamento_id', $request->departamento_id);
                        });
                    })->count(),
            ];

            return response()->json([
                'estadisticas' => $estadisticas,
                'filtros' => $request->only(['departamento_id'])
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas de provincias: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener las estadísticas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 