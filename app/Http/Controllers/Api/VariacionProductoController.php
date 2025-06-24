<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVariacionProductoRequest;
use App\Http\Requests\UpdateVariacionProductoRequest;
use App\Http\Resources\VariacionProductoResource;
use App\Models\VariacionProducto;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class VariacionProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = VariacionProducto::with(['producto', 'imagenes']);

            // Filtro por producto
            if ($request->filled('producto_id')) {
                $query->where('producto_id', $request->producto_id);
            }

            // Filtro por estado activo
            if ($request->filled('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }

            // Filtro por stock
            if ($request->filled('con_stock')) {
                if ($request->boolean('con_stock')) {
                    $query->where('stock', '>', 0);
                } else {
                    $query->where('stock', '<=', 0);
                }
            }

            // Filtro por rango de precios
            if ($request->filled('precio_min')) {
                $query->where('precio', '>=', $request->precio_min);
            }
            if ($request->filled('precio_max')) {
                $query->where('precio', '<=', $request->precio_max);
            }

            // Búsqueda por SKU
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('sku', 'like', "%{$search}%");
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            $allowedSorts = ['id', 'sku', 'precio', 'precio_oferta', 'stock', 'activo', 'created_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Paginación
            $perPage = min($request->get('per_page', 15), 100);
            $variaciones = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => VariacionProductoResource::collection($variaciones->items()),
                'pagination' => [
                    'current_page' => $variaciones->currentPage(),
                    'last_page' => $variaciones->lastPage(),
                    'per_page' => $variaciones->perPage(),
                    'total' => $variaciones->total(),
                    'from' => $variaciones->firstItem(),
                    'to' => $variaciones->lastItem(),
                ],
                'filters' => [
                    'producto_id' => $request->producto_id,
                    'activo' => $request->activo,
                    'con_stock' => $request->con_stock,
                    'precio_min' => $request->precio_min,
                    'precio_max' => $request->precio_max,
                    'search' => $request->search,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener variaciones de productos: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las variaciones de productos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVariacionProductoRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            
            // Crear la variación
            $variacion = VariacionProducto::create([
                'producto_id' => $data['producto_id'],
                'sku' => $data['sku'],
                'precio' => $data['precio'],
                'precio_oferta' => $data['precio_oferta'] ?? null,
                'stock' => $data['stock'],
                'activo' => $data['activo'] ?? true,
                'atributos' => $data['atributos'] ?? null,
            ]);

            // Sincronizar valores de atributos si se proporcionan
            if (isset($data['valores_atributos']) && is_array($data['valores_atributos'])) {
                $variacion->valoresAtributos()->sync($data['valores_atributos']);
            }

            // Cargar relaciones para la respuesta
            $variacion->load(['producto', 'imagenes', 'valoresAtributos']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Variación de producto creada exitosamente',
                'data' => new VariacionProductoResource($variacion)
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al crear variación de producto: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la variación de producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $variacion = VariacionProducto::with(['producto', 'imagenes', 'valoresAtributos'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new VariacionProductoResource($variacion)
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Variación de producto no encontrada'
            ], 404);
        } catch (Exception $e) {
            Log::error('Error al obtener variación de producto: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la variación de producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVariacionProductoRequest $request, int $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $variacion = VariacionProducto::findOrFail($id);
            $data = $request->validated();

            // Actualizar solo los campos proporcionados
            $variacion->update(array_filter([
                'producto_id' => $data['producto_id'] ?? $variacion->producto_id,
                'sku' => $data['sku'] ?? $variacion->sku,
                'precio' => $data['precio'] ?? $variacion->precio,
                'precio_oferta' => array_key_exists('precio_oferta', $data) ? $data['precio_oferta'] : $variacion->precio_oferta,
                'stock' => $data['stock'] ?? $variacion->stock,
                'activo' => array_key_exists('activo', $data) ? $data['activo'] : $variacion->activo,
                'atributos' => array_key_exists('atributos', $data) ? $data['atributos'] : $variacion->atributos,
            ], function ($value) {
                return $value !== null;
            }));

            // Sincronizar valores de atributos si se proporcionan
            if (isset($data['valores_atributos']) && is_array($data['valores_atributos'])) {
                $variacion->valoresAtributos()->sync($data['valores_atributos']);
            }

            // Cargar relaciones para la respuesta
            $variacion->load(['producto', 'imagenes', 'valoresAtributos']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Variación de producto actualizada exitosamente',
                'data' => new VariacionProductoResource($variacion)
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Variación de producto no encontrada'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar variación de producto: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la variación de producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $variacion = VariacionProducto::findOrFail($id);

            // Verificar si la variación tiene pedidos asociados
            if ($variacion->detallesPedidos()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la variación porque tiene pedidos asociados'
                ], 422);
            }

            // Eliminar imágenes asociadas
            $variacion->imagenes()->delete();

            // Desvincular valores de atributos
            $variacion->valoresAtributos()->detach();

            // Eliminar la variación
            $variacion->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Variación de producto eliminada exitosamente'
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Variación de producto no encontrada'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar variación de producto: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la variación de producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener variaciones por producto
     */
    public function byProducto(int $productoId): JsonResponse
    {
        try {
            $producto = Producto::findOrFail($productoId);
            
            $variaciones = VariacionProducto::with(['imagenes', 'valoresAtributos'])
                ->where('producto_id', $productoId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => VariacionProductoResource::collection($variaciones),
                'producto' => [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'sku' => $producto->sku,
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        } catch (Exception $e) {
            Log::error('Error al obtener variaciones por producto: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las variaciones del producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Alternar estado activo
     */
    public function toggleActivo(int $id): JsonResponse
    {
        try {
            $variacion = VariacionProducto::findOrFail($id);
            $variacion->update(['activo' => !$variacion->activo]);

            $variacion->load(['producto', 'imagenes']);

            return response()->json([
                'success' => true,
                'message' => $variacion->activo ? 'Variación activada' : 'Variación desactivada',
                'data' => new VariacionProductoResource($variacion)
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Variación de producto no encontrada'
            ], 404);
        } catch (Exception $e) {
            Log::error('Error al cambiar estado de variación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado de la variación',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualizar stock
     */
    public function updateStock(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'stock' => 'required|integer|min:0',
            'operacion' => 'sometimes|in:set,add,subtract'
        ]);

        try {
            $variacion = VariacionProducto::findOrFail($id);
            $operacion = $request->get('operacion', 'set');
            $cantidad = $request->stock;

            switch ($operacion) {
                case 'add':
                    $nuevoStock = $variacion->stock + $cantidad;
                    break;
                case 'subtract':
                    $nuevoStock = max(0, $variacion->stock - $cantidad);
                    break;
                default:
                    $nuevoStock = $cantidad;
            }

            $variacion->update(['stock' => $nuevoStock]);
            $variacion->load(['producto', 'imagenes']);

            return response()->json([
                'success' => true,
                'message' => 'Stock actualizado exitosamente',
                'data' => new VariacionProductoResource($variacion),
                'stock_anterior' => $variacion->getOriginal('stock'),
                'stock_nuevo' => $nuevoStock
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Variación de producto no encontrada'
            ], 404);
        } catch (Exception $e) {
            Log::error('Error al actualizar stock: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el stock',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }
}
