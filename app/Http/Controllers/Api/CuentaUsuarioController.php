<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DireccionResource;
use App\Http\Resources\FavoritoResource;
use App\Http\Resources\NotificacionResource;
use App\Http\Resources\PedidoResource;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Cliente;
use App\Models\Comentario;
use App\Models\CuotaCredito;
use App\Models\Direccion;
use App\Models\Favorito;
use App\Models\MetodoPago;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * @method static User|null user()
 */

class CuentaUsuarioController extends Controller
{
    /**
     * Obtener resumen del dashboard del usuario cliente
     */
    public function dashboard(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Verificar que el usuario sea cliente
        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $cliente = $user->cliente;
            
            // Estadísticas generales
            $totalPedidos = $user->pedidos()->count();
            $pedidosEntregados = $user->pedidos()->where('estado', 'entregado')->count();
            $pedidosPendientes = $user->pedidos()->whereIn('estado', ['pendiente', 'confirmado', 'preparando', 'enviado'])->count();
            $totalFavoritos = $user->favoritos()->count();
            $totalDirecciones = $user->direcciones()->count();
            $notificacionesNoLeidas = $user->notificaciones()->where('leido', false)->count();

            // Pedidos recientes (últimos 5)
            $pedidosRecientes = $user->pedidos()
                ->with(['metodoPago', 'detalles.producto'])
                ->latest()
                ->limit(5)
                ->get();

            // Favoritos recientes (últimos 5)
            $favoritosRecientes = $user->favoritos()
                ->with(['producto.categoria'])
                ->latest()
                ->limit(5)
                ->get();

            // Calcular total gastado
            $totalGastado = $user->pedidos()
                ->where('estado', 'entregado')
                ->sum('total');

            // Calcular ticket promedio
            $ticketPromedio = $pedidosEntregados > 0 ? $totalGastado / $pedidosEntregados : 0;

            // Método de pago preferido
            $metodoPagoPreferido = $user->pedidos()
                ->select('metodo_pago_id', DB::raw('count(*) as total'))
                ->groupBy('metodo_pago_id')
                ->orderBy('total', 'desc')
                ->with('metodoPago')
                ->first();

            // Categoría favorita
            $categoriaFavorita = $user->favoritos()
                ->join('productos', 'favoritos.producto_id', '=', 'productos.id')
                ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
                ->select('categorias.nombre', DB::raw('count(*) as total'))
                ->groupBy('categorias.id', 'categorias.nombre')
                ->orderBy('total', 'desc')
                ->first();

            // Información del cliente
            $datosCliente = [
                'id' => $cliente->id ?? null,
                'nombre_completo' => $cliente->nombre_completo ?? $user->name,
                'verificado' => $cliente->verificado ?? false,
                'limite_credito' => (float) ($cliente->limite_credito ?? 0),
                'estado' => $cliente->estado ?? 'activo',
                'telefono' => $cliente->telefono ?? $user->telefono,
                'dni' => $cliente->dni ?? $user->dni,
                'miembro_desde' => $user->created_at->format('Y-m-d'),
            ];

            return ApiResponse::success([
                'usuario' => new UserResource($user),
                'cliente' => $datosCliente,
                'estadisticas' => [
                    'total_pedidos' => $totalPedidos,
                    'pedidos_entregados' => $pedidosEntregados,
                    'pedidos_pendientes' => $pedidosPendientes,
                    'total_favoritos' => $totalFavoritos,
                    'total_direcciones' => $totalDirecciones,
                    'notificaciones_no_leidas' => $notificacionesNoLeidas,
                    'total_gastado' => (float) $totalGastado,
                    'ticket_promedio' => (float) $ticketPromedio,
                ],
                'pedidos_recientes' => PedidoResource::collection($pedidosRecientes),
                'favoritos_recientes' => FavoritoResource::collection($favoritosRecientes),
                'preferencias' => [
                    'metodo_pago_preferido' => $metodoPagoPreferido ? [
                        'id' => $metodoPagoPreferido->metodoPago->id,
                        'nombre' => $metodoPagoPreferido->metodoPago->nombre,
                        'veces_usado' => $metodoPagoPreferido->total,
                    ] : null,
                    'categoria_favorita' => $categoriaFavorita ? [
                        'nombre' => $categoriaFavorita->nombre,
                        'productos_favoritos' => $categoriaFavorita->total,
                    ] : null,
                ],
            ], 'Dashboard del usuario obtenido exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener el dashboard: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener pedidos del usuario con filtros
     */
    public function pedidos(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $query = $user->pedidos()
                ->with([
                    'metodoPago',
                    'detalles.producto',
                    'seguimientos' => function ($q) {
                        $q->latest('fecha_cambio')->limit(1);
                    }
                ]);

            // Filtros
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            if ($request->filled('numero_pedido')) {
                $query->where('numero_pedido', 'like', '%' . $request->numero_pedido . '%');
            }

            if ($request->filled('metodo_pago_id')) {
                $query->where('metodo_pago_id', $request->metodo_pago_id);
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            $perPage = $request->get('per_page', 10);
            $pedidos = $query->paginate($perPage);

            return ApiResponse::success([
                'pedidos' => PedidoResource::collection($pedidos),
                'pagination' => [
                    'current_page' => $pedidos->currentPage(),
                    'last_page' => $pedidos->lastPage(),
                    'per_page' => $pedidos->perPage(),
                    'total' => $pedidos->total(),
                    'from' => $pedidos->firstItem(),
                    'to' => $pedidos->lastItem(),
                ],
                'filtros_aplicados' => $request->only(['estado', 'fecha_desde', 'fecha_hasta', 'numero_pedido', 'metodo_pago_id']),
            ], 'Pedidos obtenidos exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener los pedidos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener detalle de un pedido específico
     */
    public function mostrarPedido(int $pedidoId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $pedido = $user->pedidos()
                ->with([
                    'metodoPago',
                    'direccionValidada.direccion.distrito.provincia.departamento',
                    'detalles.producto.categoria',
                    'detalles.variacion',
                    'detalles.detalleAdicionales.adicional',
                    'seguimientos.usuarioCambio',
                    'pagos',
                    'cuotasCredito' => function ($q) {
                        $q->orderBy('numero_cuota');
                    }
                ])
                ->find($pedidoId);

            if (!$pedido) {
                return ApiResponse::error('Pedido no encontrado', 404);
            }

            return ApiResponse::success([
                'pedido' => new PedidoResource($pedido),
            ], 'Detalle del pedido obtenido exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener el detalle del pedido: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener direcciones del usuario
     */
    public function direcciones(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $direcciones = $user->direcciones()
                ->with([
                    'distrito.provincia.departamento',
                    'direccionValidada.zonaReparto'
                ])
                ->orderBy('predeterminada', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return ApiResponse::success([
                'direcciones' => DireccionResource::collection($direcciones),
                'total' => $direcciones->count(),
                'predeterminada' => $direcciones->where('predeterminada', true)->first()?->id,
            ], 'Direcciones obtenidas exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener las direcciones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener favoritos del usuario con filtros
     */
    public function favoritos(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $query = $user->favoritos()
                ->with(['producto.categoria', 'producto.imagenes']);

            // Filtros
            if ($request->filled('categoria_id')) {
                $query->whereHas('producto', function ($q) use ($request) {
                    $q->where('categoria_id', $request->categoria_id);
                });
            }

            if ($request->filled('disponibles')) {
                if ($request->boolean('disponibles')) {
                    $query->whereHas('producto', function ($q) {
                        $q->where('activo', true)->where('stock', '>', 0);
                    });
                }
            }

            if ($request->filled('con_ofertas')) {
                if ($request->boolean('con_ofertas')) {
                    $query->whereHas('producto', function ($q) {
                        $q->whereNotNull('precio_oferta');
                    });
                }
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            $perPage = $request->get('per_page', 12);
            $favoritos = $query->paginate($perPage);

            // Estadísticas de favoritos
            $estadisticas = [
                'total_favoritos' => $user->favoritos()->count(),
                'con_ofertas' => $user->favoritos()->whereHas('producto', function ($q) {
                    $q->whereNotNull('precio_oferta');
                })->count(),
                'disponibles' => $user->favoritos()->whereHas('producto', function ($q) {
                    $q->where('activo', true)->where('stock', '>', 0);
                })->count(),
                'valor_total' => $user->favoritos()->with('producto')->get()->sum(function ($favorito) {
                    return $favorito->producto->precio_oferta ?? $favorito->producto->precio;
                }),
            ];

            return ApiResponse::success([
                'favoritos' => FavoritoResource::collection($favoritos),
                'pagination' => [
                    'current_page' => $favoritos->currentPage(),
                    'last_page' => $favoritos->lastPage(),
                    'per_page' => $favoritos->perPage(),
                    'total' => $favoritos->total(),
                    'from' => $favoritos->firstItem(),
                    'to' => $favoritos->lastItem(),
                ],
                'estadisticas' => $estadisticas,
                'filtros_aplicados' => $request->only(['categoria_id', 'disponibles', 'con_ofertas']),
            ], 'Favoritos obtenidos exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener los favoritos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener historial de compras con métricas
     */
    public function historial(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            // Pedidos históricos (solo entregados y cancelados)
            $query = $user->pedidos()
                ->whereIn('estado', ['entregado', 'cancelado'])
                ->with(['metodoPago', 'detalles.producto']);

            // Filtros por fechas
            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            if ($request->filled('metodo_pago_id')) {
                $query->where('metodo_pago_id', $request->metodo_pago_id);
            }

            $perPage = $request->get('per_page', 10);
            $historial = $query->latest()->paginate($perPage);

            // Métricas del historial
            $metricas = $this->calcularMetricasHistorial($user, $request);

            return ApiResponse::success([
                'historial' => PedidoResource::collection($historial),
                'pagination' => [
                    'current_page' => $historial->currentPage(),
                    'last_page' => $historial->lastPage(),
                    'per_page' => $historial->perPage(),
                    'total' => $historial->total(),
                    'from' => $historial->firstItem(),
                    'to' => $historial->lastItem(),
                ],
                'metricas' => $metricas,
                'filtros_aplicados' => $request->only(['fecha_desde', 'fecha_hasta', 'metodo_pago_id']),
            ], 'Historial de compras obtenido exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener el historial: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener notificaciones del usuario
     */
    public function notificaciones(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $query = $user->notificaciones();

            // Filtros
            if ($request->filled('leido')) {
                $query->where('leido', $request->boolean('leido'));
            }

            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            $perPage = $request->get('per_page', 15);
            $notificaciones = $query->latest()->paginate($perPage);

            // Contar no leídas
            $noLeidas = $user->notificaciones()->where('leido', false)->count();

            return ApiResponse::success([
                'notificaciones' => NotificacionResource::collection($notificaciones),
                'pagination' => [
                    'current_page' => $notificaciones->currentPage(),
                    'last_page' => $notificaciones->lastPage(),
                    'per_page' => $notificaciones->perPage(),
                    'total' => $notificaciones->total(),
                    'from' => $notificaciones->firstItem(),
                    'to' => $notificaciones->lastItem(),
                ],
                'no_leidas' => $noLeidas,
            ], 'Notificaciones obtenidas exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener las notificaciones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarNotificacionLeida(int $notificacionId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $notificacion = $user->notificaciones()->find($notificacionId);

            if (!$notificacion) {
                return ApiResponse::error('Notificación no encontrada', 404);
            }

            $notificacion->update(['leido' => true]);

            return ApiResponse::success([], 'Notificación marcada como leída');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al marcar la notificación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function marcarTodasNotificacionesLeidas(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $user->notificaciones()
                ->where('leido', false)
                ->update(['leido' => true]);

            return ApiResponse::success([], 'Todas las notificaciones marcadas como leídas');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al marcar las notificaciones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener información de crédito del usuario
     */
    public function credito(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $cliente = $user->cliente;
            $limiteCredito = (float) ($cliente->limite_credito ?? 0);

            // Calcular crédito usado
            $creditoUsado = $user->pedidos()
                ->where('tipo_pago', 'credito')
                ->whereIn('estado', ['pendiente', 'confirmado', 'preparando', 'enviado'])
                ->sum('total');

            $creditoDisponible = $limiteCredito - $creditoUsado;

            // Obtener cuotas pendientes
            $cuotasPendientes = CuotaCredito::whereHas('pedido', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('estado', 'pendiente')
            ->with('pedido')
            ->orderBy('fecha_vencimiento')
            ->get();

            // Obtener historial de pagos de crédito
            $historialCredito = $user->pedidos()
                ->where('tipo_pago', 'credito')
                ->with(['cuotasCredito', 'pagos'])
                ->latest()
                ->limit(10)
                ->get();

            return ApiResponse::success([
                'limite_credito' => $limiteCredito,
                'credito_usado' => (float) $creditoUsado,
                'credito_disponible' => (float) $creditoDisponible,
                'porcentaje_usado' => $limiteCredito > 0 ? round(($creditoUsado / $limiteCredito) * 100, 2) : 0,
                'cuotas_pendientes' => $cuotasPendientes->map(function ($cuota) {
                    return [
                        'id' => $cuota->id,
                        'pedido_numero' => $cuota->pedido->numero_pedido,
                        'numero_cuota' => $cuota->numero_cuota,
                        'monto_cuota' => (float) $cuota->monto_cuota,
                        'fecha_vencimiento' => $cuota->fecha_vencimiento->format('Y-m-d'),
                        'dias_vencimiento' => $cuota->fecha_vencimiento->diffInDays(now(), false),
                        'esta_vencida' => $cuota->fecha_vencimiento < now(),
                    ];
                }),
                'historial_credito' => $historialCredito->map(function ($pedido) {
                    return [
                        'id' => $pedido->id,
                        'numero_pedido' => $pedido->numero_pedido,
                        'total' => (float) $pedido->total,
                        'estado' => $pedido->estado,
                        'fecha' => $pedido->created_at->format('Y-m-d'),
                        'cuotas_total' => $pedido->cuotas,
                        'cuotas_pagadas' => $pedido->cuotasCredito->where('estado', 'pagado')->count(),
                    ];
                }),
            ], 'Información de crédito obtenida exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener la información de crédito: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Calcular métricas del historial de compras
     */
    private function calcularMetricasHistorial(User $user, Request $request): array
    {
        $query = $user->pedidos()->where('estado', 'entregado');

        // Aplicar filtros de fecha si existen
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        $pedidos = $query->get();
        $totalCompras = $pedidos->count();
        $totalGastado = $pedidos->sum('total');
        
        return [
            'total_compras' => $totalCompras,
            'total_gastado' => (float) $totalGastado,
            'ticket_promedio' => $totalCompras > 0 ? (float) ($totalGastado / $totalCompras) : 0,
            'primera_compra' => $user->pedidos()->where('estado', 'entregado')->oldest()->first()?->created_at?->format('Y-m-d'),
            'ultima_compra' => $user->pedidos()->where('estado', 'entregado')->latest()->first()?->created_at?->format('Y-m-d'),
            'compra_mayor' => (float) $pedidos->max('total') ?: 0,
            'compra_menor' => (float) $pedidos->min('total') ?: 0,
            'categoria_preferida' => $this->obtenerCategoriaPreferida($user),
            'metodo_pago_preferido' => $this->obtenerMetodoPagoPreferido($user),
        ];
    }

    /**
     * Obtener categoría preferida del usuario
     */
    private function obtenerCategoriaPreferida(User $user): ?array
    {
        $categoria = DB::table('pedidos')
            ->join('detalle_pedidos', 'pedidos.id', '=', 'detalle_pedidos.pedido_id')
            ->join('productos', 'detalle_pedidos.producto_id', '=', 'productos.id')
            ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
            ->where('pedidos.user_id', $user->id)
            ->where('pedidos.estado', 'entregado')
            ->select('categorias.nombre', DB::raw('count(*) as total'))
            ->groupBy('categorias.id', 'categorias.nombre')
            ->orderBy('total', 'desc')
            ->first();

        return $categoria ? [
            'nombre' => $categoria->nombre,
            'productos_comprados' => $categoria->total,
        ] : null;
    }

    /**
     * Obtener método de pago preferido del usuario
     */
    private function obtenerMetodoPagoPreferido(User $user): ?array
    {
        $metodoPago = $user->pedidos()
            ->where('estado', 'entregado')
            ->select('metodo_pago_id', DB::raw('count(*) as total'))
            ->groupBy('metodo_pago_id')
            ->orderBy('total', 'desc')
            ->with('metodoPago')
            ->first();

        return $metodoPago && $metodoPago->metodoPago ? [
            'id' => $metodoPago->metodoPago->id,
            'nombre' => $metodoPago->metodoPago->nombre,
            'veces_usado' => $metodoPago->total,
        ] : null;
    }

    /**
     * Crear nueva dirección del usuario
     */
    public function crearDireccion(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $direccion = $user->direcciones()->create($request->all());
            return ApiResponse::success(new DireccionResource($direccion), 'Dirección creada exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear la dirección: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar dirección del usuario
     */
    public function actualizarDireccion(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $direccion = $user->direcciones()->find($id);
            
            if (!$direccion) {
                return ApiResponse::error('Dirección no encontrada', 404);
            }

            $direccion->update($request->all());
            return ApiResponse::success(new DireccionResource($direccion), 'Dirección actualizada exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar la dirección: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Establecer dirección como predeterminada
     */
    public function establecerDireccionPredeterminada(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $direccion = $user->direcciones()->find($id);
            
            if (!$direccion) {
                return ApiResponse::error('Dirección no encontrada', 404);
            }

            // Quitar predeterminada de todas las direcciones del usuario
            $user->direcciones()->update(['predeterminada' => false]);
            
            // Establecer como predeterminada
            $direccion->update(['predeterminada' => true]);
            
            return ApiResponse::success([], 'Dirección establecida como predeterminada');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al establecer dirección predeterminada: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar dirección del usuario
     */
    public function eliminarDireccion(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $direccion = $user->direcciones()->find($id);
            
            if (!$direccion) {
                return ApiResponse::error('Dirección no encontrada', 404);
            }

            $direccion->delete();
            return ApiResponse::success([], 'Dirección eliminada exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar la dirección: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener categorías disponibles en favoritos
     */
    public function categoriasFavoritos(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $categorias = DB::table('favoritos')
                ->join('productos', 'favoritos.producto_id', '=', 'productos.id')
                ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
                ->where('favoritos.user_id', $user->id)
                ->select('categorias.id', 'categorias.nombre', DB::raw('count(*) as total'))
                ->groupBy('categorias.id', 'categorias.nombre')
                ->get();

            return ApiResponse::success($categorias, 'Categorías de favoritos obtenidas exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener categorías de favoritos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle favorito (agregar/quitar)
     */
    public function toggleFavorito(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->rol !== 'cliente') {
            return ApiResponse::error('Acceso denegado. Solo clientes pueden acceder a esta sección.', 403);
        }

        try {
            $productoId = $request->input('producto_id');
            
            $favorito = $user->favoritos()->where('producto_id', $productoId)->first();
            
            if ($favorito) {
                $favorito->delete();
                $mensaje = 'Producto eliminado de favoritos';
            } else {
                $user->favoritos()->create(['producto_id' => $productoId]);
                $mensaje = 'Producto agregado a favoritos';
            }

            return ApiResponse::success([], $mensaje);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al gestionar favorito: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener perfil del usuario autenticado
     */
    public function perfil(Request $request)
    {
        try {
            $user = $request->user();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Perfil obtenido exitosamente',
                'data' => [
                    'usuario' => [
                        'nombre' => $user->name,
                        'email' => $user->email,
                        'telefono' => $user->telefono ?? '',
                        'fecha_nacimiento' => $user->fecha_nacimiento ?? '',
                        'avatar' => $user->profile_image ? asset('assets/images/profiles/' . $user->profile_image) : null,
                    ],
                    'preferencias' => [
                        'notificaciones_email' => true,
                        'newsletter' => false,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el perfil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar perfil del usuario autenticado
     */
    public function actualizarPerfil(Request $request)
    {
        try {
            $user = $request->user();
            
            $validatedData = $request->validate([
                'nombre' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
                'telefono' => 'sometimes|nullable|string|max:20',
                'fecha_nacimiento' => 'sometimes|nullable|date',
            ]);

            // Actualizar solo los campos enviados
            if (isset($validatedData['nombre'])) {
                $user->name = $validatedData['nombre'];
            }
            if (isset($validatedData['email'])) {
                $user->email = $validatedData['email'];
            }
            if (isset($validatedData['telefono'])) {
                $user->telefono = $validatedData['telefono'];
            }
            if (isset($validatedData['fecha_nacimiento'])) {
                $user->fecha_nacimiento = $validatedData['fecha_nacimiento'];
            }

            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Perfil actualizado exitosamente',
                'data' => [
                    'usuario' => [
                        'nombre' => $user->name,
                        'email' => $user->email,
                        'telefono' => $user->telefono,
                        'fecha_nacimiento' => $user->fecha_nacimiento,
                    ]
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el perfil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar contraseña del usuario autenticado
     */
    public function cambiarPassword(Request $request)
    {
        try {
            $user = $request->user();
            
            $validatedData = $request->validate([
                'password_actual' => 'required|string',
                'password_nueva' => 'required|string|min:8|confirmed',
                'password_nueva_confirmation' => 'required|string|min:8',
            ]);

            // Verificar contraseña actual
            if (!Hash::check($validatedData['password_actual'], $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La contraseña actual no es correcta',
                    'errors' => [
                        'password_actual' => ['La contraseña actual no es correcta']
                    ]
                ], 422);
            }

            // Actualizar contraseña
            $user->password = Hash::make($validatedData['password_nueva']);
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Contraseña actualizada exitosamente'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al cambiar la contraseña',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}