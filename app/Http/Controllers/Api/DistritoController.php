<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\DistritoResource;
use App\Models\Distrito;
use App\Models\Provincia;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Exception;

class DistritoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Distrito::query();

            // Cargar relaciones condicionalmente
            $with = [];
            if ($request->boolean('with_provincia', false)) {
                $with[] = 'provincia';
                
                if ($request->boolean('with_departamento', false)) {
                    $with[] = 'provincia.departamento';
                }
            }
            
            if ($request->boolean('with_zonas_reparto', false)) {
                $with[] = 'zonasReparto';
            }
            
            if (!empty($with)) {
                $query->with($with);
            }

            // Filtros
            if ($request->filled('provincia_id')) {
                $query->where('provincia_id', $request->provincia_id);
            }

            if ($request->filled('departamento_id')) {
                $query->whereHas('provincia', function ($q) use ($request) {
                    $q->where('departamento_id', $request->departamento_id);
                });
            }

            if ($request->filled('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }

            if ($request->filled('disponible_delivery')) {
                $query->where('disponible_delivery', $request->boolean('disponible_delivery'));
            }

            if ($request->filled('codigo')) {
                $query->where('codigo', 'like', '%' . $request->codigo . '%');
            }

            if ($request->filled('codigo_inei')) {
                $query->where('codigo_inei', $request->codigo_inei);
            }

            if ($request->filled('codigo_postal')) {
                $query->where('codigo_postal', 'like', '%' . $request->codigo_postal . '%');
            }

            // Filtro por país a través del departamento
            if ($request->filled('pais')) {
                $query->whereHas('provincia.departamento', function ($q) use ($request) {
                    $q->where('pais', $request->pais);
                });
            }

            // Filtro por coordenadas (solo los que tienen)
            if ($request->boolean('con_coordenadas', false)) {
                $query->whereNotNull('latitud')->whereNotNull('longitud');
            }

            // Búsqueda por texto
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%")
                      ->orWhere('codigo_inei', 'like', "%{$search}%")
                      ->orWhere('codigo_postal', 'like', "%{$search}%")
                      ->orWhereHas('provincia', function ($provQuery) use ($search) {
                          $provQuery->where('nombre', 'like', "%{$search}%")
                                   ->orWhereHas('departamento', function ($deptQuery) use ($search) {
                                       $deptQuery->where('nombre', 'like', "%{$search}%");
                                   });
                      });
                });
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'nombre');
            $sortDirection = $request->get('sort_direction', 'asc');
            
            $allowedSorts = ['nombre', 'codigo', 'codigo_inei', 'codigo_postal', 'activo', 'disponible_delivery', 'created_at', 'updated_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Paginación o listado completo
            if ($request->boolean('paginate', true)) {
                $perPage = min($request->get('per_page', 15), 100);
                $distritos = $query->paginate($perPage);

                return response()->json([
                    'data' => DistritoResource::collection($distritos->items()),
                    'meta' => [
                        'current_page' => $distritos->currentPage(),
                        'last_page' => $distritos->lastPage(),
                        'per_page' => $distritos->perPage(),
                        'total' => $distritos->total(),
                        'from' => $distritos->firstItem(),
                        'to' => $distritos->lastItem(),
                    ],
                    'links' => [
                        'first' => $distritos->url(1),
                        'last' => $distritos->url($distritos->lastPage()),
                        'prev' => $distritos->previousPageUrl(),
                        'next' => $distritos->nextPageUrl(),
                    ]
                ]);
            } else {
                $distritos = $query->get();
                return response()->json([
                    'data' => DistritoResource::collection($distritos),
                    'meta' => [
                        'total' => $distritos->count(),
                    ]
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error al obtener distritos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los distritos.',
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
            'provincia_id' => 'required|exists:provincias,id',
            'nombre' => 'required|string|max:100',
            'codigo' => 'required|string|max:10|unique:distritos,codigo',
            'codigo_inei' => 'nullable|string|max:10|unique:distritos,codigo_inei',
            'codigo_postal' => 'nullable|string|max:10',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'limites_geograficos' => 'nullable|json',
            'activo' => 'boolean',
            'disponible_delivery' => 'boolean',
        ], [
            'provincia_id.required' => 'La provincia es obligatoria.',
            'provincia_id.exists' => 'La provincia seleccionada no existe.',
            'nombre.required' => 'El nombre del distrito es obligatorio.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'codigo.required' => 'El código del distrito es obligatorio.',
            'codigo.unique' => 'Ya existe un distrito con este código.',
            'codigo_inei.unique' => 'Ya existe un distrito con este código INEI.',
            'latitud.between' => 'La latitud debe estar entre -90 y 90 grados.',
            'longitud.between' => 'La longitud debe estar entre -180 y 180 grados.',
            'limites_geograficos.json' => 'Los límites geográficos deben ser un JSON válido.',
        ]);

        try {
            // Verificar que la provincia esté activa
            $provincia = Provincia::with('departamento')->findOrFail($request->provincia_id);
            if (!$provincia->activo || !$provincia->departamento->activo) {
                return response()->json([
                    'message' => 'No se puede crear un distrito en una provincia o departamento inactivo.',
                    'provincia' => $provincia->nombre,
                    'departamento' => $provincia->departamento->nombre
                ], 422);
            }

            // Validar que ambas coordenadas estén presentes o ambas ausentes
            if (($request->filled('latitud') && !$request->filled('longitud')) || 
                (!$request->filled('latitud') && $request->filled('longitud'))) {
                return response()->json([
                    'message' => 'Debe proporcionar tanto latitud como longitud, o ninguna de las dos.'
                ], 422);
            }

            $distrito = Distrito::create([
                'provincia_id' => $request->provincia_id,
                'nombre' => $request->nombre,
                'codigo' => strtoupper($request->codigo),
                'codigo_inei' => $request->codigo_inei,
                'codigo_postal' => $request->codigo_postal,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'limites_geograficos' => $request->limites_geograficos,
                'activo' => $request->boolean('activo', true),
                'disponible_delivery' => $request->boolean('disponible_delivery', false),
            ]);

            Log::info("Distrito creado exitosamente", [
                'distrito_id' => $distrito->id,
                'nombre' => $distrito->nombre,
                'codigo' => $distrito->codigo,
                'provincia_id' => $distrito->provincia_id,
                'disponible_delivery' => $distrito->disponible_delivery,
            ]);

            return (new DistritoResource($distrito->load('provincia.departamento')))
                ->response()
                ->setStatusCode(201);

        } catch (Exception $e) {
            Log::error('Error al crear distrito: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al crear el distrito.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Distrito $distrito): JsonResponse
    {
        try {
            // Cargar relaciones condicionalmente
            $with = [];
            if ($request->boolean('with_provincia', false)) {
                $with[] = 'provincia';
                
                if ($request->boolean('with_departamento', false)) {
                    $with[] = 'provincia.departamento';
                }
            }
            
            if ($request->boolean('with_zonas_reparto', false)) {
                $with[] = 'zonasReparto';
            }
            
            if (!empty($with)) {
                $distrito->load($with);
            }

            return (new DistritoResource($distrito))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error("Error al obtener distrito ID {$distrito->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener el distrito.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Distrito $distrito): JsonResponse
    {
        $request->validate([
            'provincia_id' => 'sometimes|required|exists:provincias,id',
            'nombre' => 'sometimes|required|string|max:100',
            'codigo' => [
                'sometimes', 
                'required', 
                'string', 
                'max:10',
                Rule::unique('distritos', 'codigo')->ignore($distrito->id)
            ],
            'codigo_inei' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('distritos', 'codigo_inei')->ignore($distrito->id)
            ],
            'codigo_postal' => 'nullable|string|max:10',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'limites_geograficos' => 'nullable|json',
            'activo' => 'boolean',
            'disponible_delivery' => 'boolean',
        ], [
            'provincia_id.exists' => 'La provincia seleccionada no existe.',
            'nombre.required' => 'El nombre del distrito es obligatorio.',
            'codigo.required' => 'El código del distrito es obligatorio.',
            'codigo.unique' => 'Ya existe un distrito con este código.',
            'codigo_inei.unique' => 'Ya existe un distrito con este código INEI.',
            'latitud.between' => 'La latitud debe estar entre -90 y 90 grados.',
            'longitud.between' => 'La longitud debe estar entre -180 y 180 grados.',
            'limites_geograficos.json' => 'Los límites geográficos deben ser un JSON válido.',
        ]);

        try {
            $datosActualizacion = $request->only([
                'provincia_id', 'nombre', 'codigo', 'codigo_inei', 'codigo_postal',
                'latitud', 'longitud', 'limites_geograficos', 'activo', 'disponible_delivery'
            ]);

            // Verificar que la nueva provincia esté activa si se está cambiando
            if (isset($datosActualizacion['provincia_id']) && $datosActualizacion['provincia_id'] !== $distrito->provincia_id) {
                $nuevaProvincia = Provincia::with('departamento')->findOrFail($datosActualizacion['provincia_id']);
                if (!$nuevaProvincia->activo || !$nuevaProvincia->departamento->activo) {
                    return response()->json([
                        'message' => 'No se puede mover el distrito a una provincia o departamento inactivo.',
                        'provincia' => $nuevaProvincia->nombre,
                        'departamento' => $nuevaProvincia->departamento->nombre
                    ], 422);
                }
            }

            // Validar coordenadas si se están actualizando
            if (array_key_exists('latitud', $datosActualizacion) || array_key_exists('longitud', $datosActualizacion)) {
                $nuevaLatitud = $datosActualizacion['latitud'] ?? $distrito->latitud;
                $nuevaLongitud = $datosActualizacion['longitud'] ?? $distrito->longitud;
                
                if (($nuevaLatitud !== null && $nuevaLongitud === null) || 
                    ($nuevaLatitud === null && $nuevaLongitud !== null)) {
                    return response()->json([
                        'message' => 'Debe proporcionar tanto latitud como longitud, o ninguna de las dos.'
                    ], 422);
                }
            }
            
            if (isset($datosActualizacion['codigo'])) {
                $datosActualizacion['codigo'] = strtoupper($datosActualizacion['codigo']);
            }

            $distrito->update($datosActualizacion);

            Log::info("Distrito actualizado exitosamente", [
                'distrito_id' => $distrito->id,
                'nombre' => $distrito->nombre,
                'cambios' => array_keys($datosActualizacion)
            ]);

            return (new DistritoResource($distrito->load('provincia.departamento')))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error("Error al actualizar distrito ID {$distrito->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al actualizar el distrito.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Distrito $distrito): JsonResponse
    {
        try {
            // Verificar si tiene zonas de reparto asociadas
            if ($distrito->zonasReparto()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar el distrito porque tiene zonas de reparto asociadas.',
                    'zonas_reparto_count' => $distrito->zonasReparto()->count()
                ], 422);
            }

            $distritoId = $distrito->id;
            $distritoNombre = $distrito->nombre;
            
            $distrito->delete();

            Log::info("Distrito eliminado exitosamente", [
                'distrito_id' => $distritoId,
                'nombre' => $distritoNombre
            ]);

            return response()->json(null, 204);

        } catch (Exception $e) {
            Log::error("Error al eliminar distrito ID {$distrito->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar el distrito.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle status del distrito
     */
    public function toggleStatus(Distrito $distrito): JsonResponse
    {
        try {
            $nuevoEstado = !$distrito->activo;
            
            // Si se activa el distrito, verificar que la provincia y departamento estén activos
            if ($nuevoEstado) {
                $provincia = $distrito->provincia()->with('departamento')->first();
                if (!$provincia->activo || !$provincia->departamento->activo) {
                    return response()->json([
                        'message' => 'No se puede activar el distrito porque la provincia o departamento están inactivos.',
                        'provincia' => $provincia->nombre,
                        'departamento' => $provincia->departamento->nombre
                    ], 422);
                }
            }
            
            $distrito->update(['activo' => $nuevoEstado]);

            // Si se desactiva el distrito, desactivar delivery también
            if (!$nuevoEstado) {
                $distrito->update(['disponible_delivery' => false]);
            }

            Log::info("Estado del distrito cambiado", [
                'distrito_id' => $distrito->id,
                'nombre' => $distrito->nombre,
                'estado_anterior' => !$nuevoEstado,
                'nuevo_estado' => $nuevoEstado
            ]);

            return (new DistritoResource($distrito->load('provincia.departamento')))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error("Error al cambiar estado del distrito ID {$distrito->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al cambiar el estado del distrito.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle disponibilidad de delivery
     */
    public function toggleDelivery(Distrito $distrito): JsonResponse
    {
        try {
            $nuevaDisponibilidad = !$distrito->disponible_delivery;
            
            // Si se activa delivery, verificar que el distrito esté activo
            if ($nuevaDisponibilidad && !$distrito->activo) {
                return response()->json([
                    'message' => 'No se puede activar delivery en un distrito inactivo.',
                    'distrito' => $distrito->nombre
                ], 422);
            }
            
            $distrito->update(['disponible_delivery' => $nuevaDisponibilidad]);

            Log::info("Disponibilidad de delivery cambiada", [
                'distrito_id' => $distrito->id,
                'nombre' => $distrito->nombre,
                'disponibilidad_anterior' => !$nuevaDisponibilidad,
                'nueva_disponibilidad' => $nuevaDisponibilidad
            ]);

            return (new DistritoResource($distrito->load('provincia.departamento')))
                ->response()
                ->setStatusCode(200);

        } catch (Exception $e) {
            Log::error("Error al cambiar disponibilidad de delivery del distrito ID {$distrito->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al cambiar la disponibilidad de delivery.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener distritos por provincia
     */
    public function porProvincia(Request $request, Provincia $provincia): JsonResponse
    {
        $request->validate([
            'activo' => 'boolean',
            'disponible_delivery' => 'boolean',
        ]);

        try {
            $query = $provincia->distritos();

            if ($request->filled('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }

            if ($request->filled('disponible_delivery')) {
                $query->where('disponible_delivery', $request->boolean('disponible_delivery'));
            }

            $distritos = $query->orderBy('nombre')->get();

            return response()->json([
                'data' => DistritoResource::collection($distritos),
                'provincia' => [
                    'id' => $provincia->id,
                    'nombre' => $provincia->nombre,
                    'codigo' => $provincia->codigo,
                    'activo' => $provincia->activo,
                ],
                'total' => $distritos->count(),
                'activos' => $distritos->where('activo', true)->count(),
                'con_delivery' => $distritos->where('disponible_delivery', true)->count(),
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener distritos por provincia: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los distritos de la provincia.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener distritos disponibles para delivery
     */
    public function disponiblesDelivery(Request $request): JsonResponse
    {
        try {
            $query = Distrito::with('provincia.departamento')
                ->where('activo', true)
                ->where('disponible_delivery', true);

            // Filtros opcionales
            if ($request->filled('provincia_id')) {
                $query->where('provincia_id', $request->provincia_id);
            }

            if ($request->filled('departamento_id')) {
                $query->whereHas('provincia', function ($q) use ($request) {
                    $q->where('departamento_id', $request->departamento_id);
                });
            }

            $distritos = $query->orderBy('nombre')->get();

            return response()->json([
                'data' => DistritoResource::collection($distritos),
                'total' => $distritos->count(),
                'por_provincia' => $distritos->groupBy('provincia_id')->map(function ($grupo) {
                    $provincia = $grupo->first()->provincia;
                    return [
                        'provincia_id' => $provincia->id,
                        'provincia_nombre' => $provincia->nombre,
                        'total_distritos' => $grupo->count(),
                    ];
                })->values(),
            ]);

        } catch (Exception $e) {
            Log::error("Error al obtener distritos disponibles para delivery: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener los distritos disponibles para delivery.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar distrito por coordenadas
     */
    public function buscarPorCoordenadas(Request $request): JsonResponse
    {
        $request->validate([
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'radio_km' => 'nullable|numeric|min:0.1|max:100',
        ]);

        try {
            $latitud = $request->latitud;
            $longitud = $request->longitud;
            $radioKm = $request->get('radio_km', 10);

            // Búsqueda por proximidad usando fórmula haversine
            $distritos = Distrito::with('provincia.departamento')
                ->whereNotNull('latitud')
                ->whereNotNull('longitud')
                ->selectRaw("
                    *,
                    (6371 * acos(cos(radians(?)) 
                    * cos(radians(latitud)) 
                    * cos(radians(longitud) - radians(?)) 
                    + sin(radians(?)) 
                    * sin(radians(latitud)))) AS distancia_km
                ", [$latitud, $longitud, $latitud])
                ->having('distancia_km', '<=', $radioKm)
                ->orderBy('distancia_km')
                ->get();

            return response()->json([
                'data' => $distritos->map(function ($distrito) {
                    $resource = new DistritoResource($distrito);
                    $data = $resource->toArray(request());
                    $data['distancia_km'] = round($distrito->distancia_km, 2);
                    return $data;
                }),
                'coordenadas_busqueda' => [
                    'latitud' => $latitud,
                    'longitud' => $longitud,
                    'radio_km' => $radioKm,
                ],
                'total_encontrados' => $distritos->count(),
            ]);

        } catch (Exception $e) {
            Log::error("Error al buscar distritos por coordenadas: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al buscar distritos por coordenadas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de distritos
     */
    public function estadisticas(Request $request): JsonResponse
    {
        try {
            $query = Distrito::query();
            
            // Filtros opcionales
            if ($request->filled('provincia_id')) {
                $query->where('provincia_id', $request->provincia_id);
            }

            if ($request->filled('departamento_id')) {
                $query->whereHas('provincia', function ($q) use ($request) {
                    $q->where('departamento_id', $request->departamento_id);
                });
            }

            $estadisticas = [
                'total_distritos' => (clone $query)->count(),
                'distritos_activos' => (clone $query)->where('activo', true)->count(),
                'distritos_inactivos' => (clone $query)->where('activo', false)->count(),
                'disponibles_delivery' => (clone $query)->where('disponible_delivery', true)->count(),
                'no_disponibles_delivery' => (clone $query)->where('disponible_delivery', false)->count(),
                'con_coordenadas' => (clone $query)->whereNotNull('latitud')->whereNotNull('longitud')->count(),
                'sin_coordenadas' => (clone $query)->where(function ($q) {
                    $q->whereNull('latitud')->orWhereNull('longitud');
                })->count(),
                'por_provincia' => (clone $query)
                    ->with('provincia:id,nombre')
                    ->select('provincia_id', DB::raw('count(*) as total'))
                    ->groupBy('provincia_id')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'provincia_id' => $item->provincia_id,
                            'provincia_nombre' => $item->provincia->nombre,
                            'total_distritos' => $item->total,
                        ];
                    }),
                'con_zonas_reparto' => (clone $query)->has('zonasReparto')->count(),
                'sin_zonas_reparto' => (clone $query)->doesntHave('zonasReparto')->count(),
            ];

            return response()->json([
                'estadisticas' => $estadisticas,
                'filtros' => $request->only(['provincia_id', 'departamento_id'])
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas de distritos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener las estadísticas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 