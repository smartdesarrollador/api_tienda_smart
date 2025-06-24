<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImagenProductoRequest;
use App\Http\Requests\UpdateImagenProductoRequest;
use App\Http\Resources\ImagenProductoResource;
use App\Models\ImagenProducto;
use App\Models\Producto;
use App\Models\VariacionProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImagenProductoController extends Controller
{
    /**
     * Listar imágenes con filtros avanzados
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ImagenProducto::with(['producto', 'variacion']);

            // Filtros
            if ($request->filled('producto_id')) {
                $query->where('producto_id', $request->producto_id);
            }

            if ($request->filled('variacion_id')) {
                $query->where('variacion_id', $request->variacion_id);
            }

            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            if ($request->filled('principal')) {
                $query->where('principal', filter_var($request->principal, FILTER_VALIDATE_BOOLEAN));
            }

            // Búsqueda por alt text
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('alt', 'like', "%{$search}%")
                      ->orWhere('url', 'like', "%{$search}%");
                });
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'orden');
            $sortOrder = $request->get('sort_order', 'asc');
            
            if (in_array($sortBy, ['id', 'orden', 'principal', 'created_at'])) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('orden', 'asc');
            }

            // Paginación
            $perPage = min($request->get('per_page', 15), 100);
            $imagenes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ImagenProductoResource::collection($imagenes->items()),
                'pagination' => [
                    'current_page' => $imagenes->currentPage(),
                    'last_page' => $imagenes->lastPage(),
                    'per_page' => $imagenes->perPage(),
                    'total' => $imagenes->total(),
                    'from' => $imagenes->firstItem(),
                    'to' => $imagenes->lastItem(),
                ],
                'filters' => [
                    'producto_id' => $request->producto_id,
                    'variacion_id' => $request->variacion_id,
                    'tipo' => $request->tipo,
                    'principal' => $request->principal,
                    'search' => $request->search,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar imágenes de productos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las imágenes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear nueva imagen de producto
     */
    public function store(StoreImagenProductoRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $data = $request->validated();
            
            // Verificar que el producto existe
            $producto = Producto::findOrFail($data['producto_id']);
            
            // Verificar variación si se especifica
            if (isset($data['variacion_id'])) {
                $variacion = VariacionProducto::where('id', $data['variacion_id'])
                    ->where('producto_id', $data['producto_id'])
                    ->firstOrFail();
            }

            // Procesar imagen
            if ($request->hasFile('imagen')) {
                $imageData = $this->processImage($request->file('imagen'), $producto->id, $data['variacion_id'] ?? null);
                $data['url'] = $imageData['url'];
            }

            // Ajustar orden si no se especifica
            if (!isset($data['orden'])) {
                $maxOrden = ImagenProducto::where('producto_id', $data['producto_id'])
                    ->when(isset($data['variacion_id']), function ($q) use ($data) {
                        return $q->where('variacion_id', $data['variacion_id']);
                    })
                    ->max('orden') ?? 0;
                $data['orden'] = $maxOrden + 1;
            }

            // Si se marca como principal, desmarcar otras
            if ($data['principal'] ?? false) {
                $this->unsetOtherPrincipal($data['producto_id'], $data['variacion_id'] ?? null);
            }

            $imagen = ImagenProducto::create($data);
            $imagen->load(['producto', 'variacion']);

            DB::commit();

            Log::info('Imagen de producto creada exitosamente', [
                'imagen_id' => $imagen->id,
                'producto_id' => $imagen->producto_id,
                'variacion_id' => $imagen->variacion_id,
                'url' => $imagen->url,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Imagen creada exitosamente',
                'data' => new ImagenProductoResource($imagen),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al crear imagen de producto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la imagen',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar imagen específica
     */
    public function show(ImagenProducto $imagenProducto): JsonResponse
    {
        try {
            $imagenProducto->load(['producto', 'variacion']);

            return response()->json([
                'success' => true,
                'data' => new ImagenProductoResource($imagenProducto),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al mostrar imagen de producto', [
                'imagen_id' => $imagenProducto->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la imagen',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar imagen usando POST method
     */
    public function update(UpdateImagenProductoRequest $request, ImagenProducto $imagenProducto): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $data = $request->validated();
            $oldUrl = $imagenProducto->url;

            // Procesar nueva imagen si se proporciona
            if ($request->hasFile('imagen')) {
                $imageData = $this->processImage(
                    $request->file('imagen'), 
                    $imagenProducto->producto_id, 
                    $imagenProducto->variacion_id
                );
                $data['url'] = $imageData['url'];
            }

            // Si se marca como principal, desmarcar otras
            if (($data['principal'] ?? false) && !$imagenProducto->principal) {
                $this->unsetOtherPrincipal($imagenProducto->producto_id, $imagenProducto->variacion_id);
            }

            $imagenProducto->update($data);
            $imagenProducto->load(['producto', 'variacion']);

            // Eliminar imagen anterior si se subió una nueva
            if ($request->hasFile('imagen') && $oldUrl && $oldUrl !== $data['url']) {
                $this->deleteImageFile($oldUrl);
            }

            DB::commit();

            Log::info('Imagen de producto actualizada exitosamente', [
                'imagen_id' => $imagenProducto->id,
                'old_url' => $oldUrl,
                'new_url' => $imagenProducto->url,
                'metodo' => 'POST'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Imagen actualizada exitosamente',
                'data' => new ImagenProductoResource($imagenProducto),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al actualizar imagen de producto', [
                'imagen_id' => $imagenProducto->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la imagen',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar imagen
     */
    public function destroy(ImagenProducto $imagenProducto): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $url = $imagenProducto->url;
            $imagenId = $imagenProducto->id;

            $imagenProducto->delete();

            // Eliminar archivo físico
            if ($url) {
                $this->deleteImageFile($url);
            }

            DB::commit();

            Log::info('Imagen de producto eliminada exitosamente', [
                'imagen_id' => $imagenId,
                'url' => $url,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Imagen eliminada exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al eliminar imagen de producto', [
                'imagen_id' => $imagenProducto->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la imagen',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener imágenes por producto
     */
    public function byProducto(Request $request, int $productoId): JsonResponse
    {
        try {
            $producto = Producto::findOrFail($productoId);
            
            $query = ImagenProducto::where('producto_id', $productoId)
                ->with(['variacion']);

            // Filtrar por variación si se especifica
            if ($request->filled('variacion_id')) {
                $query->where('variacion_id', $request->variacion_id);
            }

            // Filtrar por tipo si se especifica
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            $imagenes = $query->orderBy('orden', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => ImagenProductoResource::collection($imagenes),
                'producto' => [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'sku' => $producto->sku,
                ],
                'total_imagenes' => $imagenes->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener imágenes por producto', [
                'producto_id' => $productoId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las imágenes del producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener imágenes por variación
     */
    public function byVariacion(Request $request, int $variacionId): JsonResponse
    {
        try {
            $variacion = VariacionProducto::with('producto')->findOrFail($variacionId);
            
            $query = ImagenProducto::where('variacion_id', $variacionId)
                ->with(['producto']);

            // Filtrar por tipo si se especifica
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            $imagenes = $query->orderBy('orden', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => ImagenProductoResource::collection($imagenes),
                'variacion' => [
                    'id' => $variacion->id,
                    'sku' => $variacion->sku,
                    'producto' => [
                        'id' => $variacion->producto->id,
                        'nombre' => $variacion->producto->nombre,
                    ],
                ],
                'total_imagenes' => $imagenes->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener imágenes por variación', [
                'variacion_id' => $variacionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las imágenes de la variación',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar orden de imágenes usando POST method
     */
    public function updateOrder(Request $request): JsonResponse
    {
        $request->validate([
            'imagenes' => 'required|array',
            'imagenes.*.id' => 'required|integer|exists:imagenes_productos,id',
            'imagenes.*.orden' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        
        try {
            foreach ($request->imagenes as $imagenData) {
                ImagenProducto::where('id', $imagenData['id'])
                    ->update(['orden' => $imagenData['orden']]);
            }

            DB::commit();

            Log::info('Orden de imágenes actualizado exitosamente', [
                'imagenes_count' => count($request->imagenes),
                'metodo' => 'POST'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Orden de imágenes actualizado exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al actualizar orden de imágenes', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el orden de las imágenes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar imagen como principal
     */
    public function setPrincipal(ImagenProducto $imagenProducto): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            // Desmarcar otras imágenes principales
            $this->unsetOtherPrincipal($imagenProducto->producto_id, $imagenProducto->variacion_id);
            
            // Marcar esta como principal
            $imagenProducto->update(['principal' => true]);

            DB::commit();

            Log::info('Imagen marcada como principal', [
                'imagen_id' => $imagenProducto->id,
                'producto_id' => $imagenProducto->producto_id,
                'variacion_id' => $imagenProducto->variacion_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Imagen marcada como principal exitosamente',
                'data' => new ImagenProductoResource($imagenProducto->fresh(['producto', 'variacion'])),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al marcar imagen como principal', [
                'imagen_id' => $imagenProducto->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar la imagen como principal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de imágenes
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_imagenes' => ImagenProducto::count(),
                'imagenes_principales' => ImagenProducto::where('principal', true)->count(),
                'imagenes_por_producto' => ImagenProducto::whereNull('variacion_id')->count(),
                'imagenes_por_variacion' => ImagenProducto::whereNotNull('variacion_id')->count(),
                'por_tipo' => ImagenProducto::selectRaw('tipo, COUNT(*) as count')
                    ->whereNotNull('tipo')
                    ->groupBy('tipo')
                    ->pluck('count', 'tipo')
                    ->toArray(),
                'productos_con_imagenes' => ImagenProducto::distinct('producto_id')->count('producto_id'),
                'variaciones_con_imagenes' => ImagenProducto::whereNotNull('variacion_id')
                    ->distinct('variacion_id')
                    ->count('variacion_id'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de imágenes', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesar y guardar imagen
     */
    private function processImage(UploadedFile $file, int $productoId, ?int $variacionId = null): array
    {
        // Generar nombre único
        $extension = $file->getClientOriginalExtension();
        $filename = uniqid('imagen_producto_') . '_' . Str::random(10) . '.' . $extension;
        
        // Determinar directorio - usar assets/productos consistentemente
        $directory = 'assets/productos';
        if ($variacionId) {
            $directory .= '/variaciones';
        }

        // Crear directorio si no existe
        $fullPath = public_path($directory);
        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        // Procesar imagen con funciones nativas de PHP
        try {
            // Obtener información de la imagen
            $imageInfo = getimagesize($file->getPathname());
            if ($imageInfo === false) {
                throw new \Exception('Archivo no es una imagen válida');
            }

            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            $mimeType = $imageInfo['mime'];

            // Si la imagen es muy grande, redimensionar (máximo 1200px de ancho)
            if ($originalWidth > 1200) {
                $this->resizeImage($file->getPathname(), $fullPath . '/' . $filename, $mimeType, 1200);
            } else {
                // Simplemente mover el archivo si no necesita redimensionamiento
                $file->move($fullPath, $filename);
            }
            
        } catch (\Exception $e) {
            // Si hay algún error en el procesamiento, usar move simple
            $file->move($fullPath, $filename);
        }

        return [
            'url' => $directory . '/' . $filename,
            'filename' => $filename,
            'directory' => $directory,
        ];
    }

    /**
     * Redimensionar imagen usando funciones nativas de PHP
     */
    private function resizeImage(string $sourcePath, string $destinationPath, string $mimeType, int $maxWidth): void
    {
        // Crear imagen desde archivo según el tipo MIME
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                throw new \Exception('Tipo de imagen no soportado');
        }

        if ($sourceImage === false) {
            throw new \Exception('No se pudo crear la imagen desde el archivo');
        }

        // Obtener dimensiones originales
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // Calcular nuevas dimensiones manteniendo proporción
        $ratio = $originalHeight / $originalWidth;
        $newWidth = min($maxWidth, $originalWidth);
        $newHeight = (int) ($newWidth * $ratio);

        // Crear nueva imagen redimensionada
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preservar transparencia para PNG y GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefill($resizedImage, 0, 0, $transparent);
        }

        // Redimensionar imagen
        imagecopyresampled(
            $resizedImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );

        // Guardar imagen redimensionada
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($resizedImage, $destinationPath, 85);
                break;
            case 'image/png':
                imagepng($resizedImage, $destinationPath, 8);
                break;
            case 'image/gif':
                imagegif($resizedImage, $destinationPath);
                break;
            case 'image/webp':
                imagewebp($resizedImage, $destinationPath, 85);
                break;
        }

        // Liberar memoria
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
    }

    /**
     * Desmarcar otras imágenes principales
     */
    private function unsetOtherPrincipal(int $productoId, ?int $variacionId = null): void
    {
        $query = ImagenProducto::where('producto_id', $productoId)
            ->where('principal', true);

        if ($variacionId) {
            $query->where('variacion_id', $variacionId);
        } else {
            $query->whereNull('variacion_id');
        }

        $query->update(['principal' => false]);
    }

    /**
     * Eliminar archivo de imagen
     */
    private function deleteImageFile(string $url): void
    {
        try {
            $fullPath = public_path($url);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        } catch (\Exception $e) {
            Log::warning('No se pudo eliminar el archivo de imagen', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }
} 