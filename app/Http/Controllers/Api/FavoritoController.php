<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFavoritoRequest;
use App\Http\Requests\UpdateFavoritoRequest;
use App\Http\Resources\FavoritoResource;
use App\Models\Favorito;
use App\Models\User;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FavoritoController extends Controller
{
    /**
     * Listar favoritos con filtros avanzados
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $query = Favorito::with(['usuario:id,name,email,rol', 'producto.categoria:id,nombre,slug']);

            // Filtros básicos
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('producto_id')) {
                $query->where('producto_id', $request->producto_id);
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
                $dias = (int) $request->get('recientes', 7);
                $query->where('created_at', '>=', now()->subDays($dias));
            }

            if ($request->filled('categoria_id')) {
                $query->whereHas('producto', function ($productQuery) use ($request) {
                    $productQuery->where('categoria_id', $request->categoria_id);
                });
            }

            if ($request->filled('productos_activos')) {
                $productosActivos = filter_var($request->productos_activos, FILTER_VALIDATE_BOOLEAN);
                $query->whereHas('producto', function ($productQuery) use ($productosActivos) {
                    $productQuery->where('activo', $productosActivos);
                });
            }

            if ($request->filled('productos_disponibles')) {
                $productosDisponibles = filter_var($request->productos_disponibles, FILTER_VALIDATE_BOOLEAN);
                if ($productosDisponibles) {
                    $query->whereHas('producto', function ($productQuery) {
                        $productQuery->where('activo', true)->where('stock', '>', 0);
                    });
                }
            }

            if ($request->filled('con_ofertas')) {
                $conOfertas = filter_var($request->con_ofertas, FILTER_VALIDATE_BOOLEAN);
                if ($conOfertas) {
                    $query->whereHas('producto', function ($productQuery) {
                        $productQuery->whereNotNull('precio_oferta');
                    });
                }
            }

            if ($request->filled('rango_precio')) {
                $rangoPrecio = explode('-', $request->rango_precio);
                if (count($rangoPrecio) === 2) {
                    $precioMin = (float) $rangoPrecio[0];
                    $precioMax = (float) $rangoPrecio[1];
                    
                    $query->whereHas('producto', function ($productQuery) use ($precioMin, $precioMax) {
                        $productQuery->whereBetween('precio', [$precioMin, $precioMax]);
                    });
                }
            }

            // Búsqueda inteligente
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('producto', function ($productQuery) use ($search) {
                        $productQuery->where('nombre', 'LIKE', "%{$search}%")
                                   ->orWhere('descripcion', 'LIKE', "%{$search}%")
                                   ->orWhere('marca', 'LIKE', "%{$search}%")
                                   ->orWhere('modelo', 'LIKE', "%{$search}%")
                                   ->orWhere('sku', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('usuario', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'LIKE', "%{$search}%")
                                 ->orWhere('email', 'LIKE', "%{$search}%");
                    });
                });
            }

            // Ordenamiento
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $allowedSortFields = ['created_at', 'updated_at', 'user_id', 'producto_id'];
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            } else {
                // Ordenamiento por campos del producto
                if ($sortField === 'producto_nombre') {
                    $query->join('productos', 'favoritos.producto_id', '=', 'productos.id')
                          ->orderBy('productos.nombre', $sortDirection)
                          ->select('favoritos.*');
                } elseif ($sortField === 'producto_precio') {
                    $query->join('productos', 'favoritos.producto_id', '=', 'productos.id')
                          ->orderBy('productos.precio', $sortDirection)
                          ->select('favoritos.*');
                }
            }

            // Paginación
            $perPage = min((int) $request->get('per_page', 15), 100);
            $favoritos = $query->paginate($perPage);

            // Agregar información de filtros aplicados
            $favoritos->appends([
                'filters_applied' => [
                    'user_id' => $request->user_id,
                    'producto_id' => $request->producto_id,
                    'fecha_desde' => $request->fecha_desde,
                    'fecha_hasta' => $request->fecha_hasta,
                    'recientes' => $request->recientes,
                    'categoria_id' => $request->categoria_id,
                    'productos_activos' => $request->productos_activos,
                    'productos_disponibles' => $request->productos_disponibles,
                    'con_ofertas' => $request->con_ofertas,
                    'rango_precio' => $request->rango_precio,
                    'search' => $request->search,
                ]
            ]);

            return FavoritoResource::collection($favoritos);

        } catch (\Exception $e) {
            Log::error('Error al listar favoritos: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Agregar producto a favoritos
     */
    public function store(StoreFavoritoRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $favorito = Favorito::create($request->validated());

            DB::commit();

            Log::info('Producto agregado a favoritos exitosamente', [
                'favorito_id' => $favorito->id,
                'user_id' => $favorito->user_id,
                'producto_id' => $favorito->producto_id,
                'created_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado a favoritos exitosamente',
                'data' => new FavoritoResource($favorito->load(['usuario', 'producto.categoria']))
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al agregar producto a favoritos: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar el producto a favoritos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar favorito específico
     */
    public function show(Favorito $favorito): JsonResponse
    {
        try {
            $favorito->load(['usuario:id,name,email,rol,avatar', 'producto.categoria']);

            return response()->json([
                'success' => true,
                'data' => new FavoritoResource($favorito)
            ]);

        } catch (\Exception $e) {
            Log::error('Error al mostrar favorito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el favorito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar favorito (limitado, principalmente para auditoría)
     */
    public function update(UpdateFavoritoRequest $request, Favorito $favorito): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosAnteriores = $favorito->toArray();
            $favorito->update($request->validated());

            DB::commit();

            Log::info('Favorito actualizado exitosamente', [
                'favorito_id' => $favorito->id,
                'datos_anteriores' => $datosAnteriores,
                'datos_nuevos' => $favorito->fresh()->toArray(),
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Favorito actualizado exitosamente',
                'data' => new FavoritoResource($favorito->load(['usuario', 'producto.categoria']))
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar favorito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el favorito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar favorito
     */
    public function destroy(Favorito $favorito): JsonResponse
    {
        try {
            DB::beginTransaction();

            $favoritoData = $favorito->toArray();
            $favorito->delete();

            DB::commit();

            Log::info('Favorito eliminado exitosamente', [
                'favorito_eliminado' => $favoritoData,
                'deleted_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado de favoritos exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar favorito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el favorito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener favoritos de un usuario específico
     */
    public function byUsuario(Request $request, User $usuario): AnonymousResourceCollection
    {
        try {
            $query = $usuario->favoritos()->with(['producto.categoria']);

            // Aplicar filtros similares al index
            if ($request->filled('categoria_id')) {
                $query->whereHas('producto', function ($productQuery) use ($request) {
                    $productQuery->where('categoria_id', $request->categoria_id);
                });
            }

            if ($request->filled('productos_disponibles')) {
                $productosDisponibles = filter_var($request->productos_disponibles, FILTER_VALIDATE_BOOLEAN);
                if ($productosDisponibles) {
                    $query->whereHas('producto', function ($productQuery) {
                        $productQuery->where('activo', true)->where('stock', '>', 0);
                    });
                }
            }

            if ($request->filled('con_ofertas')) {
                $conOfertas = filter_var($request->con_ofertas, FILTER_VALIDATE_BOOLEAN);
                if ($conOfertas) {
                    $query->whereHas('producto', function ($productQuery) {
                        $productQuery->whereNotNull('precio_oferta');
                    });
                }
            }

            if ($request->filled('recientes')) {
                $dias = (int) $request->get('recientes', 7);
                $query->where('created_at', '>=', now()->subDays($dias));
            }

            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            if ($sortField === 'producto_nombre') {
                $query->join('productos', 'favoritos.producto_id', '=', 'productos.id')
                      ->orderBy('productos.nombre', $sortDirection)
                      ->select('favoritos.*');
            } elseif ($sortField === 'producto_precio') {
                $query->join('productos', 'favoritos.producto_id', '=', 'productos.id')
                      ->orderBy('productos.precio', $sortDirection)
                      ->select('favoritos.*');
            } else {
                $query->orderBy($sortField, $sortDirection);
            }

            $perPage = min((int) $request->get('per_page', 15), 50);
            $favoritos = $query->paginate($perPage);

            // Estadísticas del usuario
            $estadisticas = [
                'total_favoritos' => $usuario->favoritos()->count(),
                'favoritos_disponibles' => $usuario->favoritos()
                    ->whereHas('producto', function ($q) {
                        $q->where('activo', true)->where('stock', '>', 0);
                    })->count(),
                'favoritos_con_ofertas' => $usuario->favoritos()
                    ->whereHas('producto', function ($q) {
                        $q->whereNotNull('precio_oferta');
                    })->count(),
                'por_categoria' => $usuario->favoritos()
                    ->join('productos', 'favoritos.producto_id', '=', 'productos.id')
                    ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
                    ->select('categorias.nombre', DB::raw('count(*) as total'))
                    ->groupBy('categorias.id', 'categorias.nombre')
                    ->pluck('total', 'nombre')
                    ->toArray(),
                'recientes_7_dias' => $usuario->favoritos()
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'valor_total_favoritos' => $usuario->favoritos()
                    ->join('productos', 'favoritos.producto_id', '=', 'productos.id')
                    ->sum(DB::raw('COALESCE(productos.precio_oferta, productos.precio)'))
            ];

            $favoritos->appends([
                'usuario' => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'rol' => $usuario->rol
                ],
                'estadisticas' => $estadisticas
            ]);

            return FavoritoResource::collection($favoritos);

        } catch (\Exception $e) {
            Log::error('Error al obtener favoritos del usuario: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Alternar favorito (agregar si no existe, eliminar si existe)
     */
    public function toggle(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'producto_id' => 'required|exists:productos,id'
            ]);

            DB::beginTransaction();

            $favorito = Favorito::where('user_id', $request->user_id)
                               ->where('producto_id', $request->producto_id)
                               ->first();

            if ($favorito) {
                // Eliminar favorito existente
                $favorito->delete();
                $accion = 'eliminado';
                $mensaje = 'Producto eliminado de favoritos exitosamente';
                $data = null;
                
                Log::info('Favorito eliminado mediante toggle', [
                    'user_id' => $request->user_id,
                    'producto_id' => $request->producto_id,
                    'toggled_by' => Auth::id()
                ]);
            } else {
                // Crear nuevo favorito
                $favorito = Favorito::create([
                    'user_id' => $request->user_id,
                    'producto_id' => $request->producto_id
                ]);
                $accion = 'agregado';
                $mensaje = 'Producto agregado a favoritos exitosamente';
                $data = new FavoritoResource($favorito->load(['usuario', 'producto.categoria']));
                
                Log::info('Favorito agregado mediante toggle', [
                    'favorito_id' => $favorito->id,
                    'user_id' => $request->user_id,
                    'producto_id' => $request->producto_id,
                    'toggled_by' => Auth::id()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'accion' => $accion,
                'data' => $data
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al alternar favorito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar si un producto es favorito de un usuario
     */
    public function verificarFavorito(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'producto_id' => 'required|exists:productos,id'
            ]);

            $esFavorito = Favorito::where('user_id', $request->user_id)
                                 ->where('producto_id', $request->producto_id)
                                 ->exists();

            $favorito = null;
            if ($esFavorito) {
                $favorito = Favorito::where('user_id', $request->user_id)
                                   ->where('producto_id', $request->producto_id)
                                   ->with(['producto.categoria'])
                                   ->first();
            }

            return response()->json([
                'success' => true,
                'es_favorito' => $esFavorito,
                'data' => $favorito ? new FavoritoResource($favorito) : null
            ]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error al verificar favorito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el favorito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar favoritos antiguos o de productos inactivos
     */
    public function limpiarFavoritos(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'dias' => 'integer|min:1|max:365',
                'productos_inactivos' => 'boolean',
                'productos_agotados' => 'boolean',
                'user_id' => 'nullable|exists:users,id'
            ]);

            $dias = $request->get('dias');
            $productosInactivos = $request->get('productos_inactivos', false);
            $productosAgotados = $request->get('productos_agotados', false);
            $userId = $request->get('user_id');

            DB::beginTransaction();

            $query = Favorito::query();

            if ($dias) {
                $query->where('created_at', '<', now()->subDays($dias));
            }

            if ($productosInactivos) {
                $query->whereHas('producto', function ($productQuery) {
                    $productQuery->where('activo', false);
                });
            }

            if ($productosAgotados) {
                $query->whereHas('producto', function ($productQuery) {
                    $productQuery->where('stock', '<=', 0);
                });
            }

            if ($userId) {
                $query->where('user_id', $userId);
            }

            $favoritosEliminados = $query->count();
            $query->delete();

            DB::commit();

            Log::info('Favoritos limpiados', [
                'dias' => $dias,
                'productos_inactivos' => $productosInactivos,
                'productos_agotados' => $productosAgotados,
                'user_id' => $userId,
                'favoritos_eliminados' => $favoritosEliminados,
                'cleaned_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Se eliminaron {$favoritosEliminados} favoritos",
                'favoritos_eliminados' => $favoritosEliminados,
                'criterios' => [
                    'dias_antiguedad' => $dias,
                    'productos_inactivos' => $productosInactivos,
                    'productos_agotados' => $productosAgotados,
                    'user_id' => $userId
                ]
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al limpiar favoritos: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar los favoritos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener conteo de favoritos del usuario autenticado
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

            $total = Favorito::where('user_id', $user->id)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener conteo de favoritos: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el conteo de favoritos',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas del sistema de favoritos
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fecha_desde' => 'nullable|date',
                'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
                'user_id' => 'nullable|exists:users,id'
            ]);

            $query = Favorito::query();

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
                'total_favoritos' => $query->count(),
                'favoritos_recientes_24h' => (clone $query)->where('created_at', '>=', now()->subHours(24))->count(),
                'favoritos_esta_semana' => (clone $query)->where('created_at', '>=', now()->subWeek())->count(),
                'favoritos_este_mes' => (clone $query)->where('created_at', '>=', now()->subMonth())->count(),
                'usuarios_con_favoritos' => Favorito::distinct('user_id')->count(),
                'productos_favoriteados' => Favorito::distinct('producto_id')->count(),
                'promedio_favoritos_por_usuario' => 0,
                'favoritos_productos_disponibles' => 0,
                'favoritos_productos_con_ofertas' => 0,
                'valor_total_favoritos' => 0
            ];

            // Cálculos adicionales
            if ($resumenGeneral['usuarios_con_favoritos'] > 0) {
                $resumenGeneral['promedio_favoritos_por_usuario'] = round(
                    $resumenGeneral['total_favoritos'] / $resumenGeneral['usuarios_con_favoritos'], 
                    2
                );
            }

            $resumenGeneral['favoritos_productos_disponibles'] = Favorito::whereHas('producto', function ($q) {
                $q->where('activo', true)->where('stock', '>', 0);
            })->count();

            $resumenGeneral['favoritos_productos_con_ofertas'] = Favorito::whereHas('producto', function ($q) {
                $q->whereNotNull('precio_oferta');
            })->count();

            $resumenGeneral['valor_total_favoritos'] = Favorito::join('productos', 'favoritos.producto_id', '=', 'productos.id')
                ->sum(DB::raw('COALESCE(productos.precio_oferta, productos.precio)'));

            // Productos más favoriteados
            $productosMasFavoriteados = Favorito::select('producto_id', DB::raw('count(*) as total_favoritos'))
                ->with('producto:id,nombre,precio,precio_oferta,imagen_principal,activo,stock')
                ->groupBy('producto_id')
                ->orderBy('total_favoritos', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->producto->id,
                        'nombre' => $item->producto->nombre,
                        'precio' => (float) $item->producto->precio,
                        'precio_oferta' => $item->producto->precio_oferta ? (float) $item->producto->precio_oferta : null,
                        'imagen_principal' => $item->producto->imagen_principal,
                        'activo' => (bool) $item->producto->activo,
                        'stock' => $item->producto->stock,
                        'total_favoritos' => $item->total_favoritos
                    ];
                });

            // Usuarios más activos (que más productos tienen en favoritos)
            $usuariosMasActivos = Favorito::select('user_id', DB::raw('count(*) as total_favoritos'))
                ->with('usuario:id,name,email,rol')
                ->groupBy('user_id')
                ->orderBy('total_favoritos', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->usuario->id,
                        'name' => $item->usuario->name,
                        'email' => $item->usuario->email,
                        'rol' => $item->usuario->rol,
                        'total_favoritos' => $item->total_favoritos
                    ];
                });

            // Distribución por categorías
            $distribucionCategorias = Favorito::join('productos', 'favoritos.producto_id', '=', 'productos.id')
                ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
                ->select('categorias.nombre', DB::raw('count(*) as total'))
                ->groupBy('categorias.id', 'categorias.nombre')
                ->orderBy('total', 'desc')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->nombre => $item->total];
                });

            // Tendencia mensual (últimos 12 meses)
            $tendenciaMensual = collect();
            for ($i = 11; $i >= 0; $i--) {
                $fecha = now()->subMonths($i);
                $total = Favorito::whereYear('created_at', $fecha->year)
                    ->whereMonth('created_at', $fecha->month)
                    ->count();
                
                $tendenciaMensual->push([
                    'mes' => $fecha->format('Y-m'),
                    'nombre_mes' => $fecha->format('M Y'),
                    'favoritos' => $total
                ]);
            }

            // Análisis de conversión (productos favoriteados que luego se compraron)
            $analisisConversion = [
                'productos_favoriteados_comprados' => DB::table('favoritos')
                    ->join('detalles_pedido', 'favoritos.producto_id', '=', 'detalles_pedido.producto_id')
                    ->where('detalles_pedido.created_at', '>', DB::raw('favoritos.created_at'))
                    ->distinct('favoritos.id')
                    ->count(),
                'tasa_conversion' => 0
            ];

            if ($resumenGeneral['total_favoritos'] > 0) {
                $analisisConversion['tasa_conversion'] = round(
                    ($analisisConversion['productos_favoriteados_comprados'] / $resumenGeneral['total_favoritos']) * 100, 
                    2
                );
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'resumen_general' => $resumenGeneral,
                    'productos_mas_favoriteados' => $productosMasFavoriteados,
                    'usuarios_mas_activos' => $usuariosMasActivos,
                    'distribucion_categorias' => $distribucionCategorias,
                    'tendencia_mensual' => $tendenciaMensual,
                    'analisis_conversion' => $analisisConversion,
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
            Log::error('Error al obtener estadísticas de favoritos: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 