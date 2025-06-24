<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Categoria;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Test simple primero
            if ($request->query('test', false)) {
                return response()->json([
                    'message' => 'Test exitoso',
                    'productos_count' => Producto::count(),
                    'categorias_count' => \App\Models\Categoria::count()
                ], 200);
            }

            $perPage = min((int) $request->query('per_page', 60), 100);
            $query = Producto::query();

            // Filtro por categoría específica
            if ($request->has('categoria_id')) {
                $query->where('categoria_id', $request->query('categoria_id'));
            }

            // Filtro por nombre (búsqueda parcial)
            if ($request->has('nombre')) {
                $query->where('nombre', 'like', '%' . $request->query('nombre') . '%');
            }

            // Filtro por SKU
            if ($request->has('sku')) {
                $query->where('sku', 'like', '%' . $request->query('sku') . '%');
            }

            // Filtro por marca
            if ($request->has('marca')) {
                $query->where('marca', 'like', '%' . $request->query('marca') . '%');
            }

            // Filtro por modelo
            if ($request->has('modelo')) {
                $query->where('modelo', 'like', '%' . $request->query('modelo') . '%');
            }

            // Filtro por rango de precios
            if ($request->has('precio_min')) {
                $query->where('precio', '>=', (float) $request->query('precio_min'));
            }
            if ($request->has('precio_max')) {
                $query->where('precio', '<=', (float) $request->query('precio_max'));
            }

            // Filtro por stock
            if ($request->has('con_stock')) {
                $conStock = filter_var($request->query('con_stock'), FILTER_VALIDATE_BOOLEAN);
                if ($conStock) {
                    $query->where('stock', '>', 0);
                } else {
                    $query->where('stock', '=', 0);
                }
            }

            // Filtro por productos destacados
            if ($request->has('destacado')) {
                $destacado = filter_var($request->query('destacado'), FILTER_VALIDATE_BOOLEAN);
                $query->where('destacado', $destacado);
            }

            // Filtro por productos activos
            if ($request->has('activo')) {
                $activo = filter_var($request->query('activo'), FILTER_VALIDATE_BOOLEAN);
                $query->where('activo', $activo);
            }

            // Filtro por productos con imagen principal
            if ($request->has('con_imagen')) {
                $conImagen = filter_var($request->query('con_imagen'), FILTER_VALIDATE_BOOLEAN);
                if ($conImagen) {
                    $query->whereNotNull('imagen_principal');
                } else {
                    $query->whereNull('imagen_principal');
                }
            }

            // Incluir estadísticas
            if ($request->query('include_stats', false)) {
                $query->withCount(['variaciones', 'imagenes', 'comentarios', 'favoritos'])
                      ->withAvg('comentarios', 'calificacion');
            }

            // Cargar relaciones
            $with = ['categoria:id,nombre,slug'];
            if ($request->query('include_variaciones', false)) {
                $with[] = 'variaciones:id,producto_id,sku,precio,stock,activo';
            }
            if ($request->query('include_imagenes', false)) {
                $with[] = 'imagenes:id,producto_id,url,alt,principal';
            }
            $query->with($with);

            // Ordenamiento
            $orderBy = $request->query('order_by', 'nombre');
            $orderDirection = $request->query('order_direction', 'asc');
            
            if (in_array($orderBy, ['nombre', 'precio', 'stock', 'created_at', 'categoria_id'])) {
                if ($orderBy === 'categoria_id') {
                    // Ordenar por nombre de categoría
                    $query->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
                          ->orderBy('categorias.nombre', $orderDirection)
                          ->orderBy('productos.nombre', 'asc')
                          ->select('productos.*');
                } else {
                    $query->orderBy($orderBy, $orderDirection);
                }
            } else {
                $query->orderBy('nombre', 'asc');
            }

            $productos = $query->paginate($perPage);
            
            return ProductoResource::collection($productos)
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al obtener productos: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener productos.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductoRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosValidados = $request->validated();
            
            // Manejar subida de imagen principal si existe
            if ($request->hasFile('imagen_principal')) {
                $datosValidados['imagen_principal'] = $this->handleImageUpload($request->file('imagen_principal'));
            }

            $producto = Producto::create($datosValidados);
            
            DB::commit();

            Log::info("Producto creado: {$producto->id}", [
                'categoria_id' => $producto->categoria_id,
                'nombre' => $producto->nombre,
                'precio' => $producto->precio
            ]);

            return (new ProductoResource($producto->load('categoria')))
                ->response()
                ->setStatusCode(201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al crear producto: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al crear el producto.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Producto $producto): JsonResponse
    {
        try {
            $producto->load([
                'categoria:id,nombre,slug', 
                'variaciones:id,producto_id,sku,precio,precio_oferta,stock,activo',
                'imagenes:id,producto_id,url,alt,principal,orden'
            ]);
            
            return (new ProductoResource($producto))
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al obtener producto ID {$producto->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener el producto.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage using POST method.
     */
    public function update(UpdateProductoRequest $request, Producto $producto): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosValidados = $request->validated();
            $imagenAnterior = $producto->imagen_principal;
            
            // Manejar subida de nueva imagen principal
            if ($request->hasFile('imagen_principal')) {
                $datosValidados['imagen_principal'] = $this->handleImageUpload($request->file('imagen_principal'));
                
                // Eliminar imagen anterior si existe
                if ($imagenAnterior) {
                    $this->deleteImage($imagenAnterior);
                }
            }

            $producto->update($datosValidados);
            
            DB::commit();

            Log::info("Producto actualizado: {$producto->id}", [
                'cambios' => array_keys($datosValidados),
                'metodo' => 'POST'
            ]);

            return (new ProductoResource($producto->load('categoria')))
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar producto ID {$producto->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al actualizar el producto.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Producto $producto): JsonResponse
    {
        try {
            // Verificar si el producto tiene variaciones activas o pedidos asociados
            if ($producto->variaciones()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar el producto porque tiene variaciones asociadas.',
                    'variaciones_count' => $producto->variaciones()->count()
                ], 409); // Conflict
            }

            if ($producto->detallesPedidos()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar el producto porque está asociado a pedidos.',
                    'pedidos_count' => $producto->detallesPedidos()->count()
                ], 409); // Conflict
            }

            DB::beginTransaction();

            $imagenParaEliminar = $producto->imagen_principal;
            $productoId = $producto->id;
            $productoNombre = $producto->nombre;

            // Eliminar imágenes asociadas
            foreach ($producto->imagenes as $imagen) {
                $this->deleteImage($imagen->url);
            }

            $producto->delete();

            // Eliminar imagen principal si existe
            if ($imagenParaEliminar) {
                $this->deleteImage($imagenParaEliminar);
            }

            DB::commit();

            Log::info("Producto eliminado: {$productoId}", [
                'nombre' => $productoNombre
            ]);

            return response()->json(null, 204);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar producto ID {$producto->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar el producto.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get productos by categoria
     */
    public function byCategoria(Categoria $categoria): JsonResponse
    {
        try {
            $productos = $categoria->productos()
                ->where('activo', true)
                ->with('categoria:id,nombre,slug')
                ->orderBy('nombre', 'asc')
                ->get();

            return ProductoResource::collection($productos)
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al obtener productos de la categoría {$categoria->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener productos de la categoría.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle destacado status
     */
    public function toggleDestacado(Producto $producto): JsonResponse
    {
        try {
            $producto->update(['destacado' => !$producto->destacado]);
            
            Log::info("Producto destacado cambiado: {$producto->id}", [
                'destacado' => $producto->destacado
            ]);

            return (new ProductoResource($producto->load('categoria')))
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al cambiar estado destacado del producto {$producto->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al cambiar estado destacado.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle activo status
     */
    public function toggleActivo(Producto $producto): JsonResponse
    {
        try {
            $producto->update(['activo' => !$producto->activo]);
            
            Log::info("Producto activo cambiado: {$producto->id}", [
                'activo' => $producto->activo
            ]);

            return (new ProductoResource($producto->load('categoria')))
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al cambiar estado activo del producto {$producto->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al cambiar estado activo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove imagen principal from producto
     */
    public function removeImagenPrincipal(Producto $producto): JsonResponse
    {
        try {
            if (!$producto->imagen_principal) {
                return response()->json([
                    'message' => 'El producto no tiene imagen principal asociada.'
                ], 400);
            }

            DB::beginTransaction();

            $imagenParaEliminar = $producto->imagen_principal;
            $producto->update(['imagen_principal' => null]);

            $this->deleteImage($imagenParaEliminar);

            DB::commit();

            Log::info("Imagen principal eliminada del producto: {$producto->id}");

            return (new ProductoResource($producto->load('categoria')))
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar imagen principal del producto {$producto->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar la imagen principal.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get productos destacados
     */
    public function destacados(Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->query('limit', 10), 50);
            
            $productos = Producto::where('destacado', true)
                ->where('activo', true)
                ->with('categoria:id,nombre,slug')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return ProductoResource::collection($productos)
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al obtener productos destacados: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener productos destacados.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search productos
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->query('q', '');
            $limit = min((int) $request->query('limit', 10), 50);
            
            if (strlen($query) < 2) {
                return response()->json([
                    'message' => 'La búsqueda debe tener al menos 2 caracteres.',
                    'data' => []
                ], 400);
            }

            $productos = Producto::where('activo', true)
                ->where(function ($q) use ($query) {
                    $q->where('nombre', 'like', "%{$query}%")
                      ->orWhere('descripcion', 'like', "%{$query}%")
                      ->orWhere('sku', 'like', "%{$query}%")
                      ->orWhere('marca', 'like', "%{$query}%")
                      ->orWhere('modelo', 'like', "%{$query}%");
                })
                ->with('categoria:id,nombre,slug')
                ->orderBy('nombre', 'asc')
                ->limit($limit)
                ->get();

            return ProductoResource::collection($productos)
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error en búsqueda de productos: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor en la búsqueda.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for productos
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_productos' => Producto::count(),
                'productos_activos' => Producto::where('activo', true)->count(),
                'productos_destacados' => Producto::where('destacado', true)->count(),
                'productos_sin_stock' => Producto::where('stock', 0)->count(),
                'productos_con_imagen' => Producto::whereNotNull('imagen_principal')->count(),
                'valor_total_inventario' => Producto::selectRaw('SUM(precio * stock) as total')->value('total') ?? 0,
                'precio_promedio' => round((float)(Producto::avg('precio') ?? 0), 2),
                'por_categoria' => Producto::join('categorias', 'productos.categoria_id', '=', 'categorias.id')
                    ->select('categorias.nombre', DB::raw('count(*) as total'))
                    ->groupBy('categorias.id', 'categorias.nombre')
                    ->get()
                    ->pluck('total', 'nombre'),
                'top_marcas' => Producto::select('marca', DB::raw('count(*) as total_productos'))
                    ->whereNotNull('marca')
                    ->groupBy('marca')
                    ->orderByDesc('total_productos')
                    ->limit(10)
                    ->get(),
            ];

            return response()->json([
                'data' => $stats
            ], 200);
        } catch (Exception $e) {
            Log::error("Error al obtener estadísticas de productos: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener estadísticas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle image upload
     */
    private function handleImageUpload($file): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = uniqid('producto_') . '.' . $extension;
        
        // Directorio de destino
        $directory = 'assets/productos';
        $fullPath = public_path($directory);
        
        // Crear directorio si no existe
        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
        
        // Mover archivo al directorio público
        $file->move($fullPath, $filename);
        
        // Retornar la ruta relativa desde public
        return $directory . '/' . $filename;
    }

    /**
     * Delete image from storage
     */
    private function deleteImage(string $imagePath): bool
    {
        try {
            $fullPath = public_path($imagePath);
            if (file_exists($fullPath)) {
                return unlink($fullPath);
            }
            return true;
        } catch (Exception $e) {
            Log::warning("No se pudo eliminar imagen: {$imagePath}. Error: " . $e->getMessage());
            return false;
        }
    }
} 