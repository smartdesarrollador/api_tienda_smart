<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDireccionRequest;
use App\Http\Requests\UpdateDireccionRequest;
use App\Http\Resources\DireccionResource;
use App\Models\Direccion;
use App\Models\User;
use App\Models\Distrito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Exception;

class DireccionController extends Controller
{
    /**
     * Listar direcciones con filtros avanzados y búsqueda
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Direccion::with([
                'user:id,name,email,telefono',
                'distrito.provincia.departamento'
            ]);

            // Cargar relaciones adicionales condicionalmente
            if ($request->boolean('with_direccion_validada', false)) {
                $query->with('direccionValidada.zonaReparto');
            }

            // Filtros
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('distrito_id')) {
                $query->where('distrito_id', $request->distrito_id);
            }

            // Filtros por ubicación política (legacy y nuevos)
            if ($request->filled('distrito')) {
                $query->whereHas('distrito', function ($q) use ($request) {
                    $q->where('nombre', 'like', '%' . $request->distrito . '%');
                });
            }

            if ($request->filled('provincia')) {
                $query->whereHas('distrito.provincia', function ($q) use ($request) {
                    $q->where('nombre', 'like', '%' . $request->provincia . '%');
                });
            }

            if ($request->filled('departamento')) {
                $query->whereHas('distrito.provincia.departamento', function ($q) use ($request) {
                    $q->where('nombre', 'like', '%' . $request->departamento . '%');
                });
            }

            if ($request->filled('pais')) {
                $query->whereHas('distrito.provincia.departamento', function ($q) use ($request) {
                    $q->where('pais', 'like', '%' . $request->pais . '%');
                });
            }

            // Filtros por estado
            if ($request->filled('predeterminada')) {
                $query->where('predeterminada', $request->boolean('predeterminada'));
            }

            if ($request->filled('validada')) {
                $query->where('validada', $request->boolean('validada'));
            }

            // Filtros por características de la dirección
            if ($request->filled('con_coordenadas')) {
                if ($request->boolean('con_coordenadas')) {
                    $query->whereNotNull('latitud')->whereNotNull('longitud');
                } else {
                    $query->where(function ($q) {
                        $q->whereNull('latitud')->orWhereNull('longitud');
                    });
                }
            }

            if ($request->filled('con_alias')) {
                if ($request->boolean('con_alias')) {
                    $query->whereNotNull('alias');
                } else {
                    $query->whereNull('alias');
                }
            }

            if ($request->filled('con_instrucciones')) {
                if ($request->boolean('con_instrucciones')) {
                    $query->whereNotNull('instrucciones_entrega');
                } else {
                    $query->whereNull('instrucciones_entrega');
                }
            }

            // Filtro por disponibilidad de delivery
            if ($request->filled('delivery_disponible')) {
                $query->whereHas('distrito', function ($q) use ($request) {
                    $q->where('disponible_delivery', $request->boolean('delivery_disponible'));
                });
            }

            // Búsqueda inteligente mejorada
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('direccion', 'like', '%' . $search . '%')
                      ->orWhere('referencia', 'like', '%' . $search . '%')
                      ->orWhere('codigo_postal', 'like', '%' . $search . '%')
                      ->orWhere('numero_exterior', 'like', '%' . $search . '%')
                      ->orWhere('numero_interior', 'like', '%' . $search . '%')
                      ->orWhere('urbanizacion', 'like', '%' . $search . '%')
                      ->orWhere('etapa', 'like', '%' . $search . '%')
                      ->orWhere('manzana', 'like', '%' . $search . '%')
                      ->orWhere('lote', 'like', '%' . $search . '%')
                      ->orWhere('alias', 'like', '%' . $search . '%')
                      ->orWhere('instrucciones_entrega', 'like', '%' . $search . '%')
                      ->orWhereHas('distrito', function ($distritoQuery) use ($search) {
                          $distritoQuery->where('nombre', 'like', '%' . $search . '%')
                                       ->orWhereHas('provincia', function ($provQuery) use ($search) {
                                           $provQuery->where('nombre', 'like', '%' . $search . '%')
                                                    ->orWhereHas('departamento', function ($deptQuery) use ($search) {
                                                        $deptQuery->where('nombre', 'like', '%' . $search . '%');
                                                    });
                                       });
                      })
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('name', 'like', '%' . $search . '%')
                                   ->orWhere('email', 'like', '%' . $search . '%');
                      });
                });
            }

            // Ordenamiento
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $allowedSortFields = [
                'id', 'direccion', 'codigo_postal', 'numero_exterior', 'urbanizacion',
                'predeterminada', 'validada', 'alias', 'created_at', 'updated_at'
            ];
            
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            }

            // Paginación
            $perPage = min($request->get('per_page', 15), 100);
            $direcciones = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => DireccionResource::collection($direcciones->items()),
                'meta' => [
                    'current_page' => $direcciones->currentPage(),
                    'last_page' => $direcciones->lastPage(),
                    'per_page' => $direcciones->perPage(),
                    'total' => $direcciones->total(),
                    'from' => $direcciones->firstItem(),
                    'to' => $direcciones->lastItem(),
                ],
                'message' => 'Direcciones obtenidas exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener direcciones: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Crear nueva dirección
     */
    public function store(StoreDireccionRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $data = $request->validated();

            // Validar que el distrito existe y está activo
            if (isset($data['distrito_id'])) {
                $distrito = Distrito::find($data['distrito_id']);
                if (!$distrito || !$distrito->activo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El distrito seleccionado no está disponible.'
                    ], 422);
                }
            }

            // Validar coordenadas si se proporcionan
            if ((isset($data['latitud']) && !isset($data['longitud'])) || 
                (!isset($data['latitud']) && isset($data['longitud']))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar tanto latitud como longitud, o ninguna de las dos.'
                ], 422);
            }

            // Si se marca como predeterminada, desmarcar las demás del usuario
            if ($data['predeterminada'] ?? false) {
                Direccion::where('user_id', $data['user_id'])
                         ->where('predeterminada', true)
                         ->update(['predeterminada' => false]);
            }

            // Si es la primera dirección del usuario, marcarla como predeterminada
            $direccionesExistentes = Direccion::where('user_id', $data['user_id'])->count();
            if ($direccionesExistentes === 0) {
                $data['predeterminada'] = true;
            }

            // Generar alias automático si no se proporciona
            if (empty($data['alias']) && isset($data['distrito_id'])) {
                $distrito = Distrito::find($data['distrito_id']);
                $contadorDirecciones = Direccion::where('user_id', $data['user_id'])->count() + 1;
                $data['alias'] = ($distrito ? $distrito->nombre : 'Dirección') . ' ' . $contadorDirecciones;
            }

            $direccion = Direccion::create($data);
            $direccion->load([
                'user:id,name,email,telefono',
                'distrito.provincia.departamento'
            ]);

            DB::commit();

            Log::info('Dirección creada exitosamente', [
                'direccion_id' => $direccion->id,
                'user_id' => $direccion->user_id,
                'distrito_id' => $direccion->distrito_id,
                'predeterminada' => $direccion->predeterminada,
                'tiene_coordenadas' => $direccion->latitud && $direccion->longitud
            ]);

            return response()->json([
                'success' => true,
                'data' => new DireccionResource($direccion),
                'message' => 'Dirección creada exitosamente'
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error al crear dirección: ' . $e->getMessage(), [
                'request' => $request->validated(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la dirección',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Mostrar dirección específica
     */
    public function show(Request $request, Direccion $direccion): JsonResponse
    {
        try {
            // Cargar relaciones base
            $direccion->load([
                'user:id,name,email,telefono,dni',
                'distrito.provincia.departamento'
            ]);

            // Cargar relaciones adicionales condicionalmente
            if ($request->boolean('with_direccion_validada', false)) {
                $direccion->load('direccionValidada.zonaReparto');
            }

            return response()->json([
                'success' => true,
                'data' => new DireccionResource($direccion),
                'message' => 'Dirección obtenida exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener dirección: ' . $e->getMessage(), [
                'direccion_id' => $direccion->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la dirección',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Actualizar dirección
     */
    public function update(UpdateDireccionRequest $request, Direccion $direccion): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $data = $request->validated();

            // Validar que el distrito existe y está activo si se está cambiando
            if (isset($data['distrito_id']) && $data['distrito_id'] !== $direccion->distrito_id) {
                $distrito = Distrito::find($data['distrito_id']);
                if (!$distrito || !$distrito->activo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El distrito seleccionado no está disponible.'
                    ], 422);
                }
            }

            // Validar coordenadas si se están actualizando
            if (array_key_exists('latitud', $data) || array_key_exists('longitud', $data)) {
                $nuevaLatitud = $data['latitud'] ?? $direccion->latitud;
                $nuevaLongitud = $data['longitud'] ?? $direccion->longitud;
                
                if (($nuevaLatitud !== null && $nuevaLongitud === null) || 
                    ($nuevaLatitud === null && $nuevaLongitud !== null)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Debe proporcionar tanto latitud como longitud, o ninguna de las dos.'
                    ], 422);
                }
            }

            // Si se marca como predeterminada, desmarcar las demás del usuario
            if (($data['predeterminada'] ?? false) && !$direccion->predeterminada) {
                Direccion::where('user_id', $direccion->user_id)
                         ->where('id', '!=', $direccion->id)
                         ->where('predeterminada', true)
                         ->update(['predeterminada' => false]);
            }

            // No permitir desmarcar la única dirección predeterminada
            if (isset($data['predeterminada']) && !$data['predeterminada'] && $direccion->predeterminada) {
                $otrasPredet = Direccion::where('user_id', $direccion->user_id)
                                      ->where('id', '!=', $direccion->id)
                                      ->where('predeterminada', true)
                                      ->count();
                
                if ($otrasPredet === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede desmarcar la única dirección predeterminada. Marque otra como predeterminada primero.'
                    ], 422);
                }
            }

            // Si se cambia el distrito y no hay alias personalizado, actualizar alias
            if (isset($data['distrito_id']) && $data['distrito_id'] !== $direccion->distrito_id && empty($data['alias'])) {
                $nuevoDistrito = Distrito::find($data['distrito_id']);
                if ($nuevoDistrito) {
                    $contadorDirecciones = Direccion::where('user_id', $direccion->user_id)->count();
                    $data['alias'] = $nuevoDistrito->nombre . ' ' . $contadorDirecciones;
                }
            }

            $direccion->update($data);
            $direccion->load([
                'user:id,name,email,telefono',
                'distrito.provincia.departamento'
            ]);

            DB::commit();

            Log::info('Dirección actualizada exitosamente', [
                'direccion_id' => $direccion->id,
                'user_id' => $direccion->user_id,
                'cambios' => array_keys($data),
                'distrito_cambiado' => isset($data['distrito_id']) && $data['distrito_id'] !== $direccion->getOriginal('distrito_id')
            ]);

            return response()->json([
                'success' => true,
                'data' => new DireccionResource($direccion),
                'message' => 'Dirección actualizada exitosamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error al actualizar dirección: ' . $e->getMessage(), [
                'direccion_id' => $direccion->id,
                'request' => $request->validated(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la dirección',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Eliminar dirección
     */
    public function destroy(Direccion $direccion): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            // Verificar si es la única dirección del usuario
            $totalDirecciones = Direccion::where('user_id', $direccion->user_id)->count();
            
            if ($totalDirecciones === 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la única dirección del usuario'
                ], 422);
            }

            // Si es la dirección predeterminada, marcar otra como predeterminada
            if ($direccion->predeterminada) {
                $otraDireccion = Direccion::where('user_id', $direccion->user_id)
                                        ->where('id', '!=', $direccion->id)
                                        ->orderBy('created_at', 'desc')
                                        ->first();
                
                if ($otraDireccion) {
                    $otraDireccion->update(['predeterminada' => true]);
                }
            }

            $direccionId = $direccion->id;
            $userId = $direccion->user_id;
            $alias = $direccion->alias;
            
            $direccion->delete();

            DB::commit();

            Log::info('Dirección eliminada exitosamente', [
                'direccion_id' => $direccionId,
                'user_id' => $userId,
                'alias' => $alias
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dirección eliminada exitosamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error al eliminar dirección: ' . $e->getMessage(), [
                'direccion_id' => $direccion->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la dirección',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Obtener direcciones por usuario
     */
    public function byUsuario(Request $request, User $usuario): JsonResponse
    {
        try {
            $query = Direccion::where('user_id', $usuario->id)
                             ->with(['distrito.provincia.departamento']);

            // Cargar direccion validada si se solicita
            if ($request->boolean('with_direccion_validada', false)) {
                $query->with('direccionValidada.zonaReparto');
            }

            // Filtros adicionales
            if ($request->filled('validada')) {
                $query->where('validada', $request->boolean('validada'));
            }

            if ($request->filled('con_coordenadas')) {
                if ($request->boolean('con_coordenadas')) {
                    $query->whereNotNull('latitud')->whereNotNull('longitud');
                } else {
                    $query->where(function ($q) {
                        $q->whereNull('latitud')->orWhereNull('longitud');
                    });
                }
            }

            $direcciones = $query->orderBy('predeterminada', 'desc')
                                ->orderBy('created_at', 'desc')
                                ->get();

            $resumen = [
                'total_direcciones' => $direcciones->count(),
                'direccion_predeterminada' => $direcciones->where('predeterminada', true)->first()?->id,
                'direcciones_validadas' => $direcciones->where('validada', true)->count(),
                'direcciones_con_coordenadas' => $direcciones->filter(function ($dir) {
                    return $dir->latitud && $dir->longitud;
                })->count(),
                'direcciones_con_alias' => $direcciones->whereNotNull('alias')->count(),
                'direcciones_delivery_disponible' => $direcciones->filter(function ($dir) {
                    return $dir->distrito && $dir->distrito->disponible_delivery;
                })->count(),
                'distribución_geografica' => [
                    'departamentos' => $direcciones->groupBy('distrito.provincia.departamento.nombre')->map->count(),
                    'provincias' => $direcciones->groupBy('distrito.provincia.nombre')->map->count(),
                    'distritos' => $direcciones->groupBy('distrito.nombre')->map->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => DireccionResource::collection($direcciones),
                'resumen' => $resumen,
                'user' => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'telefono' => $usuario->telefono
                ],
                'message' => 'Direcciones del usuario obtenidas exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener direcciones por usuario: ' . $e->getMessage(), [
                'user_id' => $usuario->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las direcciones del usuario',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Marcar dirección como predeterminada
     */
    public function setPredeterminada(Direccion $direccion): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            // Desmarcar todas las direcciones predeterminadas del usuario
            Direccion::where('user_id', $direccion->user_id)
                     ->where('predeterminada', true)
                     ->update(['predeterminada' => false]);

            // Marcar la dirección actual como predeterminada
            $direccion->update(['predeterminada' => true]);
            $direccion->load([
                'user:id,name,email,telefono',
                'distrito.provincia.departamento'
            ]);

            DB::commit();

            Log::info('Dirección marcada como predeterminada', [
                'direccion_id' => $direccion->id,
                'user_id' => $direccion->user_id,
                'alias' => $direccion->alias
            ]);

            return response()->json([
                'success' => true,
                'data' => new DireccionResource($direccion),
                'message' => 'Dirección marcada como predeterminada exitosamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error al marcar dirección como predeterminada: ' . $e->getMessage(), [
                'direccion_id' => $direccion->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar la dirección como predeterminada',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Validar dirección manualmente
     */
    public function validar(Direccion $direccion): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $direccion->update(['validada' => true]);
            $direccion->load([
                'user:id,name,email,telefono',
                'distrito.provincia.departamento'
            ]);

            DB::commit();

            Log::info('Dirección validada manualmente', [
                'direccion_id' => $direccion->id,
                'user_id' => $direccion->user_id,
                'alias' => $direccion->alias
            ]);

            return response()->json([
                'success' => true,
                'data' => new DireccionResource($direccion),
                'message' => 'Dirección validada exitosamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error al validar dirección: ' . $e->getMessage(), [
                'direccion_id' => $direccion->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al validar la dirección',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Actualizar coordenadas de una dirección
     */
    public function actualizarCoordenadas(Request $request, Direccion $direccion): JsonResponse
    {
        $request->validate([
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
        ]);

        DB::beginTransaction();
        
        try {
            $direccion->update([
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
            ]);

            $direccion->load([
                'user:id,name,email,telefono',
                'distrito.provincia.departamento'
            ]);

            DB::commit();

            Log::info('Coordenadas de dirección actualizadas', [
                'direccion_id' => $direccion->id,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud
            ]);

            return response()->json([
                'success' => true,
                'data' => new DireccionResource($direccion),
                'message' => 'Coordenadas actualizadas exitosamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error al actualizar coordenadas: ' . $e->getMessage(), [
                'direccion_id' => $direccion->id,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar las coordenadas',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Buscar direcciones por proximidad geográfica
     */
    public function buscarPorProximidad(Request $request): JsonResponse
    {
        $request->validate([
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'radio_km' => 'nullable|numeric|min:0.1|max:50',
        ]);

        try {
            $latitud = $request->latitud;
            $longitud = $request->longitud;
            $radioKm = $request->get('radio_km', 5); // 5km por defecto

            // Fórmula haversine para calcular distancia
            $direcciones = Direccion::select('*')
                ->selectRaw("
                    (6371 * acos(
                        cos(radians(?)) * 
                        cos(radians(latitud)) * 
                        cos(radians(longitud) - radians(?)) + 
                        sin(radians(?)) * 
                        sin(radians(latitud))
                    )) AS distancia_km
                ", [$latitud, $longitud, $latitud])
                ->whereNotNull('latitud')
                ->whereNotNull('longitud')
                ->having('distancia_km', '<=', $radioKm)
                ->with([
                    'user:id,name,email,telefono',
                    'distrito.provincia.departamento'
                ])
                ->orderBy('distancia_km')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => DireccionResource::collection($direcciones),
                'meta' => [
                    'punto_referencia' => [
                        'latitud' => $latitud,
                        'longitud' => $longitud
                    ],
                    'radio_busqueda_km' => $radioKm,
                    'total_encontradas' => $direcciones->count()
                ],
                'message' => 'Direcciones por proximidad obtenidas exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error al buscar direcciones por proximidad: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar direcciones por proximidad',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Obtener direcciones por distrito
     */
    public function porDistrito(Request $request, Distrito $distrito): JsonResponse
    {
        try {
            $query = Direccion::where('distrito_id', $distrito->id)
                             ->with([
                                 'user:id,name,email,telefono',
                                 'distrito.provincia.departamento'
                             ]);

            // Filtros adicionales
            if ($request->filled('validada')) {
                $query->where('validada', $request->boolean('validada'));
            }

            if ($request->filled('predeterminada')) {
                $query->where('predeterminada', $request->boolean('predeterminada'));
            }

            if ($request->filled('con_coordenadas')) {
                if ($request->boolean('con_coordenadas')) {
                    $query->whereNotNull('latitud')->whereNotNull('longitud');
                } else {
                    $query->where(function ($q) {
                        $q->whereNull('latitud')->orWhereNull('longitud');
                    });
                }
            }

            // Ordenamiento
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            if (in_array($sortField, ['created_at', 'updated_at', 'direccion', 'alias'])) {
                $query->orderBy($sortField, $sortDirection);
            }

            // Paginación
            $perPage = min($request->get('per_page', 15), 100);
            $direcciones = $query->paginate($perPage);

            $estadisticas = [
                'total_direcciones' => Direccion::where('distrito_id', $distrito->id)->count(),
                'direcciones_validadas' => Direccion::where('distrito_id', $distrito->id)->where('validada', true)->count(),
                'direcciones_con_coordenadas' => Direccion::where('distrito_id', $distrito->id)
                    ->whereNotNull('latitud')->whereNotNull('longitud')->count(),
                'usuarios_unicos' => Direccion::where('distrito_id', $distrito->id)->distinct('user_id')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => DireccionResource::collection($direcciones->items()),
                'meta' => [
                    'current_page' => $direcciones->currentPage(),
                    'last_page' => $direcciones->lastPage(),
                    'per_page' => $direcciones->perPage(),
                    'total' => $direcciones->total(),
                    'from' => $direcciones->firstItem(),
                    'to' => $direcciones->lastItem(),
                ],
                'distrito' => [
                    'id' => $distrito->id,
                    'nombre' => $distrito->nombre,
                    'disponible_delivery' => $distrito->disponible_delivery,
                    'provincia' => $distrito->provincia->nombre ?? null,
                    'departamento' => $distrito->provincia->departamento->nombre ?? null,
                ],
                'estadisticas' => $estadisticas,
                'message' => 'Direcciones del distrito obtenidas exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener direcciones por distrito: ' . $e->getMessage(), [
                'distrito_id' => $distrito->id,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las direcciones del distrito',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Obtener conteo de direcciones del usuario autenticado
     */
    public function count(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->rol !== 'cliente') {
                return response()->json([
                    'message' => 'Usuario no autorizado'
                ], 403);
            }

            $total = Direccion::where('user_id', $user->id)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener conteo de direcciones: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el conteo de direcciones',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas del sistema de direcciones
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $fechaDesde = $request->get('fecha_desde');
            $fechaHasta = $request->get('fecha_hasta');

            $query = Direccion::query();

            if ($fechaDesde) {
                $query->where('created_at', '>=', $fechaDesde);
            }

            if ($fechaHasta) {
                $query->where('created_at', '<=', $fechaHasta . ' 23:59:59');
            }

            // Estadísticas generales mejoradas
            $resumenGeneral = [
                'total_direcciones' => $query->count(),
                'direcciones_predeterminadas' => $query->where('predeterminada', true)->count(),
                'direcciones_validadas' => $query->where('validada', true)->count(),
                'direcciones_con_coordenadas' => $query->whereNotNull('latitud')->whereNotNull('longitud')->count(),
                'direcciones_con_alias' => $query->whereNotNull('alias')->count(),
                'direcciones_con_instrucciones' => $query->whereNotNull('instrucciones_entrega')->count(),
                'usuarios_con_direcciones' => $query->distinct('user_id')->count(),
                'promedio_direcciones_por_usuario' => round($query->count() / max($query->distinct('user_id')->count(), 1), 2)
            ];

            // Estadísticas de delivery
            $estadisticasDelivery = [
                'direcciones_delivery_disponible' => $query->whereHas('distrito', function ($q) {
                    $q->where('disponible_delivery', true);
                })->count(),
                'direcciones_sin_delivery' => $query->whereHas('distrito', function ($q) {
                    $q->where('disponible_delivery', false);
                })->count(),
                'porcentaje_delivery_disponible' => 0
            ];

            if ($resumenGeneral['total_direcciones'] > 0) {
                $estadisticasDelivery['porcentaje_delivery_disponible'] = round(
                    ($estadisticasDelivery['direcciones_delivery_disponible'] / $resumenGeneral['total_direcciones']) * 100, 
                    2
                );
            }

            // Distribución geográfica actualizada
            $porDepartamento = $query->join('distritos', 'direcciones.distrito_id', '=', 'distritos.id')
                                   ->join('provincias', 'distritos.provincia_id', '=', 'provincias.id')
                                   ->join('departamentos', 'provincias.departamento_id', '=', 'departamentos.id')
                                   ->select('departamentos.nombre as departamento', DB::raw('count(*) as total'))
                                   ->groupBy('departamentos.nombre')
                                   ->orderBy('total', 'desc')
                                   ->get();

            $porProvincia = $query->join('distritos', 'direcciones.distrito_id', '=', 'distritos.id')
                                 ->join('provincias', 'distritos.provincia_id', '=', 'provincias.id')
                                 ->select('provincias.nombre as provincia', DB::raw('count(*) as total'))
                                 ->groupBy('provincias.nombre')
                                 ->orderBy('total', 'desc')
                                 ->limit(10)
                                 ->get();

            $porDistrito = $query->join('distritos', 'direcciones.distrito_id', '=', 'distritos.id')
                                ->select('distritos.nombre as distrito', DB::raw('count(*) as total'))
                                ->groupBy('distritos.nombre')
                                ->orderBy('total', 'desc')
                                ->limit(15)
                                ->get();

            // Estadísticas de calidad de direcciones
            $calidadDirecciones = [
                'direcciones_completas' => $query->whereNotNull('direccion')
                    ->whereNotNull('distrito_id')
                    ->where(function ($q) {
                        $q->whereNotNull('referencia')
                          ->orWhereNotNull('numero_exterior')
                          ->orWhere(function ($subQ) {
                              $subQ->whereNotNull('manzana')->whereNotNull('lote');
                          });
                    })->count(),
                'direcciones_con_numeracion' => $query->where(function ($q) {
                    $q->whereNotNull('numero_exterior')
                      ->orWhere(function ($subQ) {
                          $subQ->whereNotNull('manzana')->whereNotNull('lote');
                      });
                })->count(),
                'direcciones_con_urbanizacion' => $query->whereNotNull('urbanizacion')->count(),
                'direcciones_con_codigo_postal' => $query->whereNotNull('codigo_postal')->count(),
            ];

            // Usuarios con más direcciones
            $usuariosConMasDirecciones = $query->select('user_id', DB::raw('count(*) as total_direcciones'))
                                              ->with('user:id,name,email')
                                              ->groupBy('user_id')
                                              ->orderBy('total_direcciones', 'desc')
                                              ->limit(10)
                                              ->get()
                                              ->map(function ($item) {
                                                  return [
                                                      'user_id' => $item->user_id,
                                                      'nombre' => $item->user->name ?? 'Usuario eliminado',
                                                      'email' => $item->user->email ?? 'N/A',
                                                      'total_direcciones' => $item->total_direcciones
                                                  ];
                                              });

            // Tendencia mensual (últimos 12 meses)
            $tendenciaMensual = Direccion::select(
                                    DB::raw('YEAR(created_at) as año'),
                                    DB::raw('MONTH(created_at) as mes'),
                                    DB::raw('COUNT(*) as total_direcciones'),
                                    DB::raw('SUM(CASE WHEN validada = 1 THEN 1 ELSE 0 END) as direcciones_validadas'),
                                    DB::raw('SUM(CASE WHEN latitud IS NOT NULL AND longitud IS NOT NULL THEN 1 ELSE 0 END) as con_coordenadas')
                                )
                                ->where('created_at', '>=', now()->subMonths(12))
                                ->groupBy('año', 'mes')
                                ->orderBy('año', 'desc')
                                ->orderBy('mes', 'desc')
                                ->get()
                                ->map(function ($item) {
                                    return [
                                        'periodo' => $item->año . '-' . str_pad($item->mes, 2, '0', STR_PAD_LEFT),
                                        'total_direcciones' => $item->total_direcciones,
                                        'direcciones_validadas' => $item->direcciones_validadas,
                                        'con_coordenadas' => $item->con_coordenadas
                                    ];
                                });

            // Top distritos con más direcciones
            $topDistritos = Direccion::join('distritos', 'direcciones.distrito_id', '=', 'distritos.id')
                                    ->select(
                                        'distritos.nombre as distrito',
                                        'distritos.disponible_delivery',
                                        DB::raw('count(*) as total_direcciones'),
                                        DB::raw('SUM(CASE WHEN direcciones.validada = 1 THEN 1 ELSE 0 END) as direcciones_validadas')
                                    )
                                    ->groupBy('distritos.id', 'distritos.nombre', 'distritos.disponible_delivery')
                                    ->orderBy('total_direcciones', 'desc')
                                    ->limit(10)
                                    ->get()
                                    ->map(function ($item) {
                                        return [
                                            'distrito' => $item->distrito,
                                            'total_direcciones' => $item->total_direcciones,
                                            'direcciones_validadas' => $item->direcciones_validadas,
                                            'disponible_delivery' => (bool) $item->disponible_delivery,
                                            'porcentaje_validadas' => $item->total_direcciones > 0 
                                                ? round(($item->direcciones_validadas / $item->total_direcciones) * 100, 2) 
                                                : 0
                                        ];
                                    });

            return response()->json([
                'success' => true,
                'data' => [
                    'resumen_general' => $resumenGeneral,
                    'estadisticas_delivery' => $estadisticasDelivery,
                    'calidad_direcciones' => $calidadDirecciones,
                    'distribucion_geografica' => [
                        'por_departamento' => $porDepartamento,
                        'por_provincia' => $porProvincia,
                        'por_distrito' => $porDistrito
                    ],
                    'top_distritos' => $topDistritos,
                    'usuarios_con_mas_direcciones' => $usuariosConMasDirecciones,
                    'tendencia_mensual' => $tendenciaMensual
                ],
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas de direcciones: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }
} 