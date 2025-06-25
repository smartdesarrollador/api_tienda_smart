<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificacionRequest;
use App\Http\Requests\UpdateNotificacionRequest;
use App\Http\Resources\NotificacionResource;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NotificacionController extends Controller
{
    /**
     * Listar notificaciones con filtros avanzados
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $query = Notificacion::with(['usuario:id,name,email,rol']);

            // Filtros básicos
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('tipo')) {
                if (str_contains($request->tipo, ',')) {
                    $tipos = explode(',', $request->tipo);
                    $query->whereIn('tipo', $tipos);
                } else {
                    $query->where('tipo', $request->tipo);
                }
            }

            if ($request->filled('leido')) {
                $leido = filter_var($request->leido, FILTER_VALIDATE_BOOLEAN);
                $query->where('leido', $leido);
            }

            // Filtros de fecha
            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            // Filtros especiales
            if ($request->filled('recientes')) {
                $horas = (int) $request->get('recientes', 24);
                $query->where('created_at', '>=', now()->subHours($horas));
            }

            if ($request->filled('prioridad')) {
                $tiposPorPrioridad = [
                    'alta' => ['pago', 'stock'],
                    'media' => ['pedido', 'recordatorio'],
                    'baja' => ['promocion', 'bienvenida', 'sistema']
                ];
                
                if (isset($tiposPorPrioridad[$request->prioridad])) {
                    $query->whereIn('tipo', $tiposPorPrioridad[$request->prioridad]);
                }
            }

            if ($request->filled('requiere_accion')) {
                $requiereAccion = filter_var($request->requiere_accion, FILTER_VALIDATE_BOOLEAN);
                if ($requiereAccion) {
                    $query->whereIn('tipo', ['pago', 'pedido', 'stock', 'recordatorio'])
                          ->where('leido', false);
                }
            }

            // Búsqueda inteligente
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('titulo', 'LIKE', "%{$search}%")
                      ->orWhere('mensaje', 'LIKE', "%{$search}%")
                      ->orWhere('tipo', 'LIKE', "%{$search}%")
                      ->orWhereHas('usuario', function ($userQuery) use ($search) {
                          $userQuery->where('name', 'LIKE', "%{$search}%")
                                   ->orWhere('email', 'LIKE', "%{$search}%");
                      });
                });
            }

            // Ordenamiento
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $allowedSortFields = ['created_at', 'updated_at', 'titulo', 'tipo', 'leido'];
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            }

            // Paginación
            $perPage = min((int) $request->get('per_page', 15), 100);
            $notificaciones = $query->paginate($perPage);

            // Agregar información de filtros aplicados
            $notificaciones->appends([
                'filters_applied' => [
                    'user_id' => $request->user_id,
                    'tipo' => $request->tipo,
                    'leido' => $request->leido,
                    'fecha_desde' => $request->fecha_desde,
                    'fecha_hasta' => $request->fecha_hasta,
                    'recientes' => $request->recientes,
                    'prioridad' => $request->prioridad,
                    'requiere_accion' => $request->requiere_accion,
                    'search' => $request->search,
                ]
            ]);

            return NotificacionResource::collection($notificaciones);

        } catch (\Exception $e) {
            Log::error('Error al listar notificaciones: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crear nueva notificación
     */
    public function store(StoreNotificacionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $notificacion = Notificacion::create($request->validated());

            DB::commit();

            Log::info('Notificación creada exitosamente', [
                'notificacion_id' => $notificacion->id,
                'user_id' => $notificacion->user_id,
                'tipo' => $notificacion->tipo,
                'created_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notificación creada exitosamente',
                'data' => new NotificacionResource($notificacion->load('usuario'))
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear notificación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar notificación específica
     */
    public function show(Notificacion $notificacion): JsonResponse
    {
        try {
            $notificacion->load('usuario:id,name,email,rol,avatar');

            return response()->json([
                'success' => true,
                'data' => new NotificacionResource($notificacion)
            ]);

        } catch (\Exception $e) {
            Log::error('Error al mostrar notificación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar notificación
     */
    public function update(UpdateNotificacionRequest $request, Notificacion $notificacion): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosAnteriores = $notificacion->toArray();
            $notificacion->update($request->validated());

            DB::commit();

            Log::info('Notificación actualizada exitosamente', [
                'notificacion_id' => $notificacion->id,
                'datos_anteriores' => $datosAnteriores,
                'datos_nuevos' => $notificacion->fresh()->toArray(),
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notificación actualizada exitosamente',
                'data' => new NotificacionResource($notificacion->load('usuario'))
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar notificación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar notificación
     */
    public function destroy(Notificacion $notificacion): JsonResponse
    {
        try {
            DB::beginTransaction();

            $notificacionData = $notificacion->toArray();
            $notificacion->delete();

            DB::commit();

            Log::info('Notificación eliminada exitosamente', [
                'notificacion_eliminada' => $notificacionData,
                'deleted_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notificación eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar notificación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener notificaciones de un usuario específico
     */
    public function byUsuario(Request $request, User $usuario): AnonymousResourceCollection
    {
        try {
            $query = $usuario->notificaciones();

            // Aplicar filtros similares al index
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            if ($request->filled('leido')) {
                $leido = filter_var($request->leido, FILTER_VALIDATE_BOOLEAN);
                $query->where('leido', $leido);
            }

            if ($request->filled('recientes')) {
                $horas = (int) $request->get('recientes', 24);
                $query->where('created_at', '>=', now()->subHours($horas));
            }

            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            $perPage = min((int) $request->get('per_page', 15), 50);
            $notificaciones = $query->paginate($perPage);

            // Estadísticas del usuario
            $estadisticas = [
                'total_notificaciones' => $usuario->notificaciones()->count(),
                'no_leidas' => $usuario->notificaciones()->where('leido', false)->count(),
                'leidas' => $usuario->notificaciones()->where('leido', true)->count(),
                'por_tipo' => $usuario->notificaciones()
                    ->select('tipo', DB::raw('count(*) as total'))
                    ->groupBy('tipo')
                    ->pluck('total', 'tipo')
                    ->toArray(),
                'recientes_24h' => $usuario->notificaciones()
                    ->where('created_at', '>=', now()->subHours(24))
                    ->count()
            ];

            $notificaciones->appends([
                'usuario' => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'rol' => $usuario->rol
                ],
                'estadisticas' => $estadisticas
            ]);

            return NotificacionResource::collection($notificaciones);

        } catch (\Exception $e) {
            Log::error('Error al obtener notificaciones del usuario: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarLeida(Notificacion $notificacion): JsonResponse
    {
        try {
            if ($notificacion->leido) {
                return response()->json([
                    'success' => false,
                    'message' => 'La notificación ya está marcada como leída'
                ], 422);
            }

            DB::beginTransaction();

            $notificacion->update(['leido' => true]);

            DB::commit();

            Log::info('Notificación marcada como leída', [
                'notificacion_id' => $notificacion->id,
                'user_id' => $notificacion->user_id,
                'marked_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída',
                'data' => new NotificacionResource($notificacion->load('usuario'))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al marcar notificación como leída: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar la notificación como leída',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar notificación como no leída
     */
    public function marcarNoLeida(Notificacion $notificacion): JsonResponse
    {
        try {
            if (!$notificacion->leido) {
                return response()->json([
                    'success' => false,
                    'message' => 'La notificación ya está marcada como no leída'
                ], 422);
            }

            DB::beginTransaction();

            $notificacion->update(['leido' => false]);

            DB::commit();

            Log::info('Notificación marcada como no leída', [
                'notificacion_id' => $notificacion->id,
                'user_id' => $notificacion->user_id,
                'marked_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como no leída',
                'data' => new NotificacionResource($notificacion->load('usuario'))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al marcar notificación como no leída: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar la notificación como no leída',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar todas las notificaciones de un usuario como leídas
     */
    public function marcarTodasLeidas(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id'
            ]);

            DB::beginTransaction();

            $notificacionesActualizadas = Notificacion::where('user_id', $request->user_id)
                ->where('leido', false)
                ->update(['leido' => true]);

            DB::commit();

            Log::info('Todas las notificaciones marcadas como leídas', [
                'user_id' => $request->user_id,
                'notificaciones_actualizadas' => $notificacionesActualizadas,
                'marked_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Se marcaron {$notificacionesActualizadas} notificaciones como leídas",
                'notificaciones_actualizadas' => $notificacionesActualizadas
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al marcar todas las notificaciones como leídas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar las notificaciones como leídas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar notificaciones antiguas
     */
    public function limpiarAntiguas(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'dias' => 'integer|min:1|max:365',
                'solo_leidas' => 'boolean',
                'user_id' => 'nullable|exists:users,id'
            ]);

            $dias = $request->get('dias', 30);
            $soloLeidas = $request->get('solo_leidas', true);
            $userId = $request->get('user_id');

            DB::beginTransaction();

            $query = Notificacion::where('created_at', '<', now()->subDays($dias));

            if ($soloLeidas) {
                $query->where('leido', true);
            }

            if ($userId) {
                $query->where('user_id', $userId);
            }

            $notificacionesEliminadas = $query->count();
            $query->delete();

            DB::commit();

            Log::info('Notificaciones antiguas eliminadas', [
                'dias' => $dias,
                'solo_leidas' => $soloLeidas,
                'user_id' => $userId,
                'notificaciones_eliminadas' => $notificacionesEliminadas,
                'cleaned_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Se eliminaron {$notificacionesEliminadas} notificaciones antiguas",
                'notificaciones_eliminadas' => $notificacionesEliminadas,
                'criterios' => [
                    'dias_antiguedad' => $dias,
                    'solo_leidas' => $soloLeidas,
                    'user_id' => $userId
                ]
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al limpiar notificaciones antiguas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar las notificaciones antiguas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas del sistema de notificaciones
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fecha_desde' => 'nullable|date',
                'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
                'user_id' => 'nullable|exists:users,id'
            ]);

            $query = Notificacion::query();

            // Aplicar filtros de fecha
            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Estadísticas generales
            $resumenGeneral = [
                'total_notificaciones' => $query->count(),
                'notificaciones_leidas' => (clone $query)->where('leido', true)->count(),
                'notificaciones_no_leidas' => (clone $query)->where('leido', false)->count(),
                'notificaciones_recientes_24h' => (clone $query)->where('created_at', '>=', now()->subHours(24))->count(),
                'notificaciones_esta_semana' => (clone $query)->where('created_at', '>=', now()->subWeek())->count(),
                'notificaciones_este_mes' => (clone $query)->where('created_at', '>=', now()->subMonth())->count(),
            ];

            // Distribución por tipo
            $distribucionTipos = (clone $query)->select('tipo', DB::raw('count(*) as total'))
                ->groupBy('tipo')
                ->orderBy('total', 'desc')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->tipo => $item->total];
                });

            // Distribución por estado de lectura
            $distribucionLectura = (clone $query)->select('leido', DB::raw('count(*) as total'))
                ->groupBy('leido')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->leido ? 'leidas' : 'no_leidas' => $item->total];
                });

            // Usuarios más activos (que más notificaciones reciben)
            $usuariosMasActivos = Notificacion::select('user_id', DB::raw('count(*) as total_notificaciones'))
                ->with('usuario:id,name,email,rol')
                ->groupBy('user_id')
                ->orderBy('total_notificaciones', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->usuario->id,
                        'name' => $item->usuario->name,
                        'email' => $item->usuario->email,
                        'rol' => $item->usuario->rol,
                        'total_notificaciones' => $item->total_notificaciones
                    ];
                });

            // Tipos más frecuentes
            $tiposMasFrecuentes = (clone $query)->select('tipo', DB::raw('count(*) as total'))
                ->groupBy('tipo')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) use ($resumenGeneral) {
                    return [
                        'tipo' => $item->tipo,
                        'total' => $item->total,
                        'porcentaje' => round(($item->total / max($resumenGeneral['total_notificaciones'], 1)) * 100, 2)
                    ];
                });

            // Tendencia mensual (últimos 12 meses)
            $tendenciaMensual = collect();
            for ($i = 11; $i >= 0; $i--) {
                $fecha = now()->subMonths($i);
                $total = Notificacion::whereYear('created_at', $fecha->year)
                    ->whereMonth('created_at', $fecha->month)
                    ->count();
                
                $tendenciaMensual->push([
                    'mes' => $fecha->format('Y-m'),
                    'nombre_mes' => $fecha->format('M Y'),
                    'notificaciones' => $total
                ]);
            }

            // Notificaciones que requieren acción
            $requierenAccion = (clone $query)->whereIn('tipo', ['pago', 'pedido', 'stock', 'recordatorio'])
                ->where('leido', false)
                ->count();

            // Promedio de notificaciones por usuario
            $totalUsuarios = User::count();
            $promedioNotificacionesPorUsuario = $totalUsuarios > 0 
                ? round($resumenGeneral['total_notificaciones'] / $totalUsuarios, 2) 
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'resumen_general' => array_merge($resumenGeneral, [
                        'requieren_accion' => $requierenAccion,
                        'promedio_por_usuario' => $promedioNotificacionesPorUsuario
                    ]),
                    'distribucion_tipos' => $distribucionTipos,
                    'distribucion_lectura' => $distribucionLectura,
                    'usuarios_mas_activos' => $usuariosMasActivos,
                    'tipos_mas_frecuentes' => $tiposMasFrecuentes,
                    'tendencia_mensual' => $tendenciaMensual,
                ],
                'periodo' => [
                    'fecha_desde' => $request->fecha_desde,
                    'fecha_hasta' => $request->fecha_hasta,
                    'user_id' => $request->user_id
                ]
            ]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de notificaciones: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar notificación masiva
     */
    public function enviarMasiva(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'titulo' => 'required|string|max:255',
                'mensaje' => 'required|string|max:1000',
                'tipo' => 'required|string|in:promocion,sistema,bienvenida,recordatorio,general',
                'usuarios' => 'required|array|min:1',
                'usuarios.*' => 'exists:users,id',
                'programada_para' => 'nullable|date|after:now'
            ]);

            DB::beginTransaction();

            $notificacionesCreadas = 0;
            $errores = [];

            foreach ($request->usuarios as $userId) {
                try {
                    Notificacion::create([
                        'user_id' => $userId,
                        'titulo' => $request->titulo,
                        'mensaje' => $request->mensaje,
                        'tipo' => $request->tipo,
                        'leido' => false,
                        'created_at' => $request->programada_para ?? now()
                    ]);
                    $notificacionesCreadas++;
                } catch (\Exception $e) {
                    $errores[] = "Usuario {$userId}: " . $e->getMessage();
                }
            }

            DB::commit();

            Log::info('Notificación masiva enviada', [
                'titulo' => $request->titulo,
                'tipo' => $request->tipo,
                'usuarios_objetivo' => count($request->usuarios),
                'notificaciones_creadas' => $notificacionesCreadas,
                'errores' => count($errores),
                'sent_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Notificación masiva enviada exitosamente",
                'notificaciones_creadas' => $notificacionesCreadas,
                'usuarios_objetivo' => count($request->usuarios),
                'errores' => $errores,
                'programada_para' => $request->programada_para
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al enviar notificación masiva: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la notificación masiva',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 