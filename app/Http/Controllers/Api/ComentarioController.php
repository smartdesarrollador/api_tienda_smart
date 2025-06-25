<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComentarioRequest;
use App\Http\Requests\UpdateComentarioRequest;
use App\Http\Resources\ComentarioResource;
use App\Models\Comentario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ComentarioController extends Controller
{
    /**
     * Listar comentarios con filtros avanzados y búsqueda
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Comentario::with(['usuario', 'producto']);

            // Filtros
            if ($request->filled('producto_id')) {
                $query->where('producto_id', $request->producto_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('calificacion')) {
                $query->where('calificacion', $request->calificacion);
            }

            if ($request->filled('aprobado')) {
                $query->where('aprobado', filter_var($request->aprobado, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('con_respuesta')) {
                $conRespuesta = filter_var($request->con_respuesta, FILTER_VALIDATE_BOOLEAN);
                if ($conRespuesta) {
                    $query->whereNotNull('respuesta_admin');
                } else {
                    $query->whereNull('respuesta_admin');
                }
            }

            // Filtros por fechas
            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            // Filtros por calificación mínima y máxima
            if ($request->filled('calificacion_min')) {
                $query->where('calificacion', '>=', $request->calificacion_min);
            }

            if ($request->filled('calificacion_max')) {
                $query->where('calificacion', '<=', $request->calificacion_max);
            }

            // Búsqueda inteligente
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('comentario', 'LIKE', "%{$search}%")
                      ->orWhere('titulo', 'LIKE', "%{$search}%")
                      ->orWhere('respuesta_admin', 'LIKE', "%{$search}%")
                      ->orWhereHas('usuario', function ($userQuery) use ($search) {
                          $userQuery->where('name', 'LIKE', "%{$search}%")
                                   ->orWhere('email', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('producto', function ($productQuery) use ($search) {
                          $productQuery->where('nombre', 'LIKE', "%{$search}%")
                                      ->orWhere('sku', 'LIKE', "%{$search}%");
                      });
                });
            }

            // Ordenamiento
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $allowedSortFields = ['created_at', 'calificacion', 'aprobado', 'titulo'];
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            }

            // Paginación
            $perPage = min($request->get('per_page', 15), 100);
            $comentarios = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ComentarioResource::collection($comentarios->items()),
                'meta' => [
                    'current_page' => $comentarios->currentPage(),
                    'last_page' => $comentarios->lastPage(),
                    'per_page' => $comentarios->perPage(),
                    'total' => $comentarios->total(),
                    'from' => $comentarios->firstItem(),
                    'to' => $comentarios->lastItem(),
                ],
                'filters_applied' => $request->only([
                    'producto_id', 'user_id', 'calificacion', 'aprobado', 'con_respuesta',
                    'fecha_desde', 'fecha_hasta', 'calificacion_min', 'calificacion_max', 'search'
                ]),
            ]);

        } catch (Exception $e) {
            Log::error('Error al listar comentarios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los comentarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo comentario
     */
    public function store(StoreComentarioRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $comentario = Comentario::create($request->validated());

            // Cargar relaciones
            $comentario->load(['usuario', 'producto']);

            DB::commit();

            Log::info('Comentario creado exitosamente', [
                'comentario_id' => $comentario->id,
                'producto_id' => $comentario->producto_id,
                'user_id' => $comentario->user_id,
                'calificacion' => $comentario->calificacion
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comentario creado exitosamente',
                'data' => new ComentarioResource($comentario),
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al crear comentario: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el comentario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un comentario específico
     */
    public function show(Comentario $comentario): JsonResponse
    {
        try {
            $comentario->load(['usuario', 'producto']);

            return response()->json([
                'success' => true,
                'data' => new ComentarioResource($comentario),
            ]);

        } catch (Exception $e) {
            Log::error('Error al mostrar comentario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el comentario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un comentario
     */
    public function update(UpdateComentarioRequest $request, Comentario $comentario): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $oldData = $comentario->toArray();
            
            $comentario->update($request->validated());
            
            // Cargar relaciones
            $comentario->load(['usuario', 'producto']);

            DB::commit();

            Log::info('Comentario actualizado exitosamente', [
                'comentario_id' => $comentario->id,
                'cambios' => array_diff_assoc($request->validated(), $oldData)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comentario actualizado exitosamente',
                'data' => new ComentarioResource($comentario),
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar comentario: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el comentario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un comentario
     */
    public function destroy(Comentario $comentario): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $comentarioData = [
                'id' => $comentario->id,
                'producto_id' => $comentario->producto_id,
                'user_id' => $comentario->user_id,
                'titulo' => $comentario->titulo
            ];

            $comentario->delete();

            DB::commit();

            Log::info('Comentario eliminado exitosamente', $comentarioData);

            return response()->json([
                'success' => true,
                'message' => 'Comentario eliminado exitosamente',
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar comentario: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el comentario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener comentarios por producto específico
     */
    public function byProducto(Producto $producto, Request $request): JsonResponse
    {
        try {
            $query = $producto->comentarios()->with(['usuario']);

            // Filtros específicos para comentarios de producto
            if ($request->filled('aprobado')) {
                $query->where('aprobado', filter_var($request->aprobado, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('calificacion')) {
                $query->where('calificacion', $request->calificacion);
            }

            // Ordenamiento por defecto: más recientes primero
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $allowedSortFields = ['created_at', 'calificacion'];
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            }

            $perPage = min($request->get('per_page', 10), 50);
            $comentarios = $query->paginate($perPage);

            // Estadísticas del producto
            $estadisticas = [
                'total_comentarios' => $producto->comentarios()->count(),
                'comentarios_aprobados' => $producto->comentarios()->where('aprobado', true)->count(),
                'promedio_calificacion' => round($producto->comentarios()->where('aprobado', true)->avg('calificacion'), 2),
                'distribucion_calificaciones' => [
                    '5_estrellas' => $producto->comentarios()->where('aprobado', true)->where('calificacion', 5)->count(),
                    '4_estrellas' => $producto->comentarios()->where('aprobado', true)->where('calificacion', 4)->count(),
                    '3_estrellas' => $producto->comentarios()->where('aprobado', true)->where('calificacion', 3)->count(),
                    '2_estrellas' => $producto->comentarios()->where('aprobado', true)->where('calificacion', 2)->count(),
                    '1_estrella' => $producto->comentarios()->where('aprobado', true)->where('calificacion', 1)->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => ComentarioResource::collection($comentarios->items()),
                'meta' => [
                    'current_page' => $comentarios->currentPage(),
                    'last_page' => $comentarios->lastPage(),
                    'per_page' => $comentarios->perPage(),
                    'total' => $comentarios->total(),
                ],
                'producto' => [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'slug' => $producto->slug,
                ],
                'estadisticas' => $estadisticas,
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener comentarios por producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los comentarios del producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar un comentario
     */
    public function aprobar(Comentario $comentario): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $comentario->update(['aprobado' => true]);
            $comentario->load(['usuario', 'producto']);

            DB::commit();

            Log::info('Comentario aprobado exitosamente', [
                'comentario_id' => $comentario->id,
                'producto_id' => $comentario->producto_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comentario aprobado exitosamente',
                'data' => new ComentarioResource($comentario),
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al aprobar comentario: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar el comentario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechazar un comentario
     */
    public function rechazar(Comentario $comentario): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $comentario->update(['aprobado' => false]);
            $comentario->load(['usuario', 'producto']);

            DB::commit();

            Log::info('Comentario rechazado exitosamente', [
                'comentario_id' => $comentario->id,
                'producto_id' => $comentario->producto_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comentario rechazado exitosamente',
                'data' => new ComentarioResource($comentario),
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al rechazar comentario: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar el comentario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Responder a un comentario (solo administradores)
     */
    public function responder(Request $request, Comentario $comentario): JsonResponse
    {
        $request->validate([
            'respuesta_admin' => 'required|string|min:10|max:500',
        ]);

        DB::beginTransaction();
        
        try {
            $comentario->update([
                'respuesta_admin' => $request->respuesta_admin,
            ]);
            
            $comentario->load(['usuario', 'producto']);

            DB::commit();

            Log::info('Respuesta agregada al comentario exitosamente', [
                'comentario_id' => $comentario->id,
                'respuesta_length' => strlen($request->respuesta_admin)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Respuesta agregada exitosamente',
                'data' => new ComentarioResource($comentario),
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al responder comentario: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar la respuesta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas completas del sistema de comentarios
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            // Filtros de fecha
            $fechaDesde = $request->get('fecha_desde');
            $fechaHasta = $request->get('fecha_hasta');

            $query = Comentario::query();
            
            if ($fechaDesde) {
                $query->whereDate('created_at', '>=', $fechaDesde);
            }
            
            if ($fechaHasta) {
                $query->whereDate('created_at', '<=', $fechaHasta);
            }

            // Resumen general
            $resumenGeneral = [
                'total_comentarios' => $query->count(),
                'comentarios_aprobados' => $query->where('aprobado', true)->count(),
                'comentarios_pendientes' => $query->where('aprobado', false)->count(),
                'comentarios_con_respuesta' => $query->whereNotNull('respuesta_admin')->count(),
                'promedio_calificacion_general' => round($query->where('aprobado', true)->avg('calificacion'), 2),
            ];

            // Distribución por calificaciones
            $distribucionCalificaciones = [
                '5_estrellas' => $query->where('aprobado', true)->where('calificacion', 5)->count(),
                '4_estrellas' => $query->where('aprobado', true)->where('calificacion', 4)->count(),
                '3_estrellas' => $query->where('aprobado', true)->where('calificacion', 3)->count(),
                '2_estrellas' => $query->where('aprobado', true)->where('calificacion', 2)->count(),
                '1_estrella' => $query->where('aprobado', true)->where('calificacion', 1)->count(),
            ];

            // Productos más comentados
            $productosMasComentados = Producto::withCount(['comentarios' => function ($q) use ($fechaDesde, $fechaHasta) {
                    if ($fechaDesde) $q->whereDate('created_at', '>=', $fechaDesde);
                    if ($fechaHasta) $q->whereDate('created_at', '<=', $fechaHasta);
                }])
                ->having('comentarios_count', '>', 0)
                ->orderBy('comentarios_count', 'desc')
                ->limit(10)
                ->get(['id', 'nombre', 'slug'])
                ->map(function ($producto) {
                    return [
                        'id' => $producto->id,
                        'nombre' => $producto->nombre,
                        'slug' => $producto->slug,
                        'total_comentarios' => $producto->comentarios_count,
                    ];
                });

            // Productos mejor calificados
            $productosMejorCalificados = Producto::select('productos.*')
                ->join('comentarios', 'productos.id', '=', 'comentarios.producto_id')
                ->where('comentarios.aprobado', true)
                ->when($fechaDesde, function ($q) use ($fechaDesde) {
                    return $q->whereDate('comentarios.created_at', '>=', $fechaDesde);
                })
                ->when($fechaHasta, function ($q) use ($fechaHasta) {
                    return $q->whereDate('comentarios.created_at', '<=', $fechaHasta);
                })
                ->groupBy('productos.id')
                ->havingRaw('COUNT(comentarios.id) >= 3') // Mínimo 3 comentarios
                ->orderByRaw('AVG(comentarios.calificacion) DESC')
                ->limit(10)
                ->get()
                ->map(function ($producto) {
                    $promedio = $producto->comentarios()->where('aprobado', true)->avg('calificacion');
                    $total = $producto->comentarios()->where('aprobado', true)->count();
                    
                    return [
                        'id' => $producto->id,
                        'nombre' => $producto->nombre,
                        'slug' => $producto->slug,
                        'promedio_calificacion' => round($promedio, 2),
                        'total_comentarios' => $total,
                    ];
                });

            // Usuarios más activos
            $usuariosMasActivos = User::withCount(['comentarios' => function ($q) use ($fechaDesde, $fechaHasta) {
                    if ($fechaDesde) $q->whereDate('created_at', '>=', $fechaDesde);
                    if ($fechaHasta) $q->whereDate('created_at', '<=', $fechaHasta);
                }])
                ->having('comentarios_count', '>', 0)
                ->orderBy('comentarios_count', 'desc')
                ->limit(10)
                ->get(['id', 'name', 'email'])
                ->map(function ($usuario) {
                    return [
                        'id' => $usuario->id,
                        'nombre' => $usuario->name,
                        'email' => $usuario->email,
                        'total_comentarios' => $usuario->comentarios_count,
                    ];
                });

            // Tendencia mensual (últimos 12 meses)
            $tendenciaMensual = collect();
            for ($i = 11; $i >= 0; $i--) {
                $fecha = now()->subMonths($i);
                $comentarios = Comentario::whereYear('created_at', $fecha->year)
                    ->whereMonth('created_at', $fecha->month)
                    ->count();
                
                $tendenciaMensual->push([
                    'mes' => $fecha->format('Y-m'),
                    'nombre_mes' => $fecha->format('M Y'),
                    'comentarios' => $comentarios,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'resumen_general' => $resumenGeneral,
                    'distribucion_calificaciones' => $distribucionCalificaciones,
                    'productos_mas_comentados' => $productosMasComentados,
                    'productos_mejor_calificados' => $productosMejorCalificados,
                    'usuarios_mas_activos' => $usuariosMasActivos,
                    'tendencia_mensual' => $tendenciaMensual,
                ],
                'periodo' => [
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas de comentarios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 