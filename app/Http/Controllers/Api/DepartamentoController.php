<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartamentoResource;
use App\Models\Departamento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Exception;

class DepartamentoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Departamento::query();

            // Cargar relaciones condicionalmente
            $with = [];
            if ($request->boolean('with_provincias', false)) {
                $with[] = 'provincias';
                
                if ($request->boolean('with_distritos', false)) {
                    $with[] = 'provincias.distritos';
                }
            }
            
            if (!empty($with)) {
                $query->with($with);
            }

            // Filtros
            if ($request->filled('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }

            if ($request->filled('pais')) {
                $query->where('pais', $request->pais);
            }

            if ($request->filled('codigo')) {
                $query->where('codigo', 'like', '%' . $request->codigo . '%');
            }

            if ($request->filled('codigo_inei')) {
                $query->where('codigo_inei', $request->codigo_inei);
            }

            // Búsqueda por texto
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%")
                      ->orWhere('codigo_inei', 'like', "%{$search}%");
                });
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'nombre');
            $sortDirection = $request->get('sort_direction', 'asc');
            
            $allowedSorts = ['nombre', 'codigo', 'codigo_inei', 'pais', 'activo', 'created_at', 'updated_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Paginación o listado completo
            if ($request->boolean('paginate', true)) {
                $perPage = min($request->get('per_page', 15), 100);
                $departamentos = $query->paginate($perPage);

                return response()->json([
                    'data' => DepartamentoResource::collection($departamentos->items()),
                    'meta' => [
                        'current_page' => $departamentos->currentPage(),
                        'last_page' => $departamentos->lastPage(),
                        'per_page' => $departamentos->perPage(),
                        'total' => $departamentos->total(),
                        'from' => $departamentos->firstItem(),
                        'to' => $departamentos->lastItem(),
                    ],
                    'links' => [
                        'first' => $departamentos->url(1),
                        'last' => $departamentos->url($departamentos->lastPage()),
                        'prev' => $departamentos->previousPageUrl(),
                        'next' => $departamentos->nextPageUrl(),
                    ]
                ]);
            } else {
                $departamentos = $query->get();
                return response()->json([
                    'data' => DepartamentoResource::collection($departamentos),
                    'meta' => [
                        'total' => $departamentos->count(),
                    ]
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error al obtener departamentos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los departamentos.',
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
            'nombre' => 'required|string|max:100',
            'codigo' => 'required|string|max:10|unique:departamentos,codigo',
            'codigo_inei' => 'nullable|string|max:10|unique:departamentos,codigo_inei',
            'pais' => 'required|string|max:100',
            'activo' => 'boolean',
        ], [
            'nombre.required' => 'El nombre del departamento es obligatorio.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'codigo.required' => 'El código del departamento es obligatorio.',
            'codigo.unique' => 'Ya existe un departamento con este código.',
            'codigo_inei.unique' => 'Ya existe un departamento con este código INEI.',
            'pais.required' => 'El país es obligatorio.',
        ]);

        try {
            $departamento = Departamento::create([
                'nombre' => $request->nombre,
                'codigo' => strtoupper($request->codigo),
                'codigo_inei' => $request->codigo_inei,
                'pais' => $request->pais,
                'activo' => $request->boolean('activo', true),
            ]);

            Log::info("Departamento creado exitosamente", [
                'departamento_id' => $departamento->id,
                'nombre' => $departamento->nombre,
                'codigo' => $departamento->codigo,
            ]);

            return (new DepartamentoResource($departamento))
                ->response()
                ->setStatusCode(201);

        } catch (Exception $e) {
            Log::error('Error al crear departamento: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al crear el departamento.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Departamento $departamento): JsonResponse
    {
        try {
            // Cargar relaciones condicionalmente
            $with = [];
            if ($request->boolean('with_provincias', false)) {
                $with[] = 'provincias';
                
                if ($request->boolean('with_distritos', false)) {
                    $with[] = 'provincias.distritos';
                }
            }
            
            if (!empty($with)) {
                $departamento->load($with);
            }

            return (new DepartamentoResource($departamento))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error("Error al obtener departamento ID {$departamento->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener el departamento.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Departamento $departamento): JsonResponse
    {
        $request->validate([
            'nombre' => 'sometimes|required|string|max:100',
            'codigo' => [
                'sometimes', 
                'required', 
                'string', 
                'max:10',
                Rule::unique('departamentos', 'codigo')->ignore($departamento->id)
            ],
            'codigo_inei' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('departamentos', 'codigo_inei')->ignore($departamento->id)
            ],
            'pais' => 'sometimes|required|string|max:100',
            'activo' => 'boolean',
        ], [
            'nombre.required' => 'El nombre del departamento es obligatorio.',
            'codigo.required' => 'El código del departamento es obligatorio.',
            'codigo.unique' => 'Ya existe un departamento con este código.',
            'codigo_inei.unique' => 'Ya existe un departamento con este código INEI.',
            'pais.required' => 'El país es obligatorio.',
        ]);

        try {
            $datosActualizacion = $request->only(['nombre', 'codigo', 'codigo_inei', 'pais', 'activo']);
            
            if (isset($datosActualizacion['codigo'])) {
                $datosActualizacion['codigo'] = strtoupper($datosActualizacion['codigo']);
            }

            $departamento->update($datosActualizacion);

            Log::info("Departamento actualizado exitosamente", [
                'departamento_id' => $departamento->id,
                'nombre' => $departamento->nombre,
                'cambios' => array_keys($datosActualizacion)
            ]);

            return (new DepartamentoResource($departamento))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error("Error al actualizar departamento ID {$departamento->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al actualizar el departamento.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Departamento $departamento): JsonResponse
    {
        try {
            // Verificar si tiene provincias asociadas
            if ($departamento->provincias()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar el departamento porque tiene provincias asociadas.',
                    'provincias_count' => $departamento->provincias()->count()
                ], 422);
            }

            $departamentoId = $departamento->id;
            $departamentoNombre = $departamento->nombre;
            
            $departamento->delete();

            Log::info("Departamento eliminado exitosamente", [
                'departamento_id' => $departamentoId,
                'nombre' => $departamentoNombre
            ]);

            return response()->json(null, 204);

        } catch (Exception $e) {
            Log::error("Error al eliminar departamento ID {$departamento->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar el departamento.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle status del departamento
     */
    public function toggleStatus(Departamento $departamento): JsonResponse
    {
        try {
            $nuevoEstado = !$departamento->activo;
            $departamento->update(['activo' => $nuevoEstado]);

            // Si se desactiva el departamento, desactivar también sus provincias
            if (!$nuevoEstado) {
                $departamento->provincias()->update(['activo' => false]);
            }

            Log::info("Estado del departamento cambiado", [
                'departamento_id' => $departamento->id,
                'nombre' => $departamento->nombre,
                'estado_anterior' => !$nuevoEstado,
                'nuevo_estado' => $nuevoEstado
            ]);

            return (new DepartamentoResource($departamento))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error("Error al cambiar estado del departamento ID {$departamento->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al cambiar el estado del departamento.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener departamentos por país
     */
    public function porPais(Request $request): JsonResponse
    {
        $request->validate([
            'pais' => 'required|string|max:100',
            'activo' => 'boolean',
        ]);

        try {
            $query = Departamento::where('pais', $request->pais);

            if ($request->filled('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }

            // Cargar provincias si se solicita
            if ($request->boolean('with_provincias', false)) {
                $query->with('provincias');
            }

            $departamentos = $query->orderBy('nombre')->get();

            return response()->json([
                'data' => DepartamentoResource::collection($departamentos),
                'pais' => $request->pais,
                'total' => $departamentos->count(),
                'activos' => $departamentos->where('activo', true)->count(),
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener departamentos por país: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los departamentos por país.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de departamentos
     */
    public function estadisticas(): JsonResponse
    {
        try {
            $estadisticas = [
                'total_departamentos' => Departamento::count(),
                'departamentos_activos' => Departamento::where('activo', true)->count(),
                'departamentos_inactivos' => Departamento::where('activo', false)->count(),
                'por_pais' => Departamento::select('pais', DB::raw('count(*) as total'))
                    ->groupBy('pais')
                    ->orderBy('total', 'desc')
                    ->get(),
                'con_provincias' => Departamento::has('provincias')->count(),
                'sin_provincias' => Departamento::doesntHave('provincias')->count(),
                'total_provincias' => DB::table('provincias')->count(),
                'total_distritos' => DB::table('distritos')->count(),
            ];

            return response()->json([
                'estadisticas' => $estadisticas
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas de departamentos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener las estadísticas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 