<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    /**
     * Listar todas las categorías con filtros y paginación
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Categoria::with(['categoriaPadre', 'subcategorias'])
            ->withCount(['productos', 'subcategorias']);

        // Filtro por estado activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        // Filtro por categoría padre
        if ($request->has('categoria_padre_id')) {
            if ($request->categoria_padre_id === 'null') {
                $query->whereNull('categoria_padre_id');
            } else {
                $query->where('categoria_padre_id', $request->categoria_padre_id);
            }
        }

        // Búsqueda por nombre
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'LIKE', "%{$search}%")
                  ->orWhere('descripcion', 'LIKE', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'orden');
        $sortDirection = $request->get('sort_direction', 'asc');
        
        if (in_array($sortBy, ['id', 'nombre', 'orden', 'created_at', 'updated_at', 'productos_count'])) {
            if ($sortBy === 'productos_count') {
                $query->orderBy('productos_count', $sortDirection);
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }
        } else {
            $query->orderBy('orden')->orderBy('nombre');
        }

        $perPage = min($request->get('per_page', 15), 100);
        $categorias = $query->paginate($perPage);

        return CategoriaResource::collection($categorias);
    }

    /**
     * Obtener categorías en estructura jerárquica (árbol)
     */
    public function tree(): JsonResponse
    {
        $categorias = Cache::remember('categorias_tree', 3600, function () {
            return Categoria::with(['subcategorias.subcategorias.subcategorias'])
                ->whereNull('categoria_padre_id')
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get();
        });

        return response()->json([
            'data' => CategoriaResource::collection($categorias),
            'meta' => [
                'total_categorias' => $categorias->count(),
                'cache_enabled' => true
            ]
        ]);
    }

    /**
     * Obtener solo categorías principales (sin padre)
     */
    public function principales(): AnonymousResourceCollection
    {
        $categorias = Categoria::whereNull('categoria_padre_id')
            ->withCount(['productos', 'subcategorias'])
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return CategoriaResource::collection($categorias);
    }

    /**
     * Mostrar una categoría específica
     */
    public function show(string $id): CategoriaResource
    {
        $categoria = Categoria::with([
                'categoriaPadre',
                'subcategorias' => function($query) {
                    $query->where('activo', true)->orderBy('orden');
                },
                'productos' => function($query) {
                    $query->where('activo', true)->limit(10);
                }
            ])
            ->withCount(['productos', 'subcategorias'])
            ->findOrFail($id);

        return new CategoriaResource($categoria);
    }

    /**
     * Obtener categoría por slug
     */
    public function bySlug(string $slug): CategoriaResource
    {
        $categoria = Categoria::with([
                'categoriaPadre',
                'subcategorias' => function($query) {
                    $query->where('activo', true)->orderBy('orden');
                },
                'productos' => function($query) {
                    $query->where('activo', true)->limit(10);
                }
            ])
            ->withCount(['productos', 'subcategorias'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new CategoriaResource($categoria);
    }

    /**
     * Obtener categorías en formato plano para un selector (dropdown)
     */
    public function forSelect(): JsonResponse
    {
        $categorias = Cache::remember('categorias_for_select', 3600, function () {
            $categoriasPrincipales = Categoria::whereNull('categoria_padre_id')
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get();

            $listaPlana = [];
            $this->buildCategoryList($categoriasPrincipales, $listaPlana);

            return $listaPlana;
        });

        return response()->json($categorias);
    }

    /**
     * Validar si el nombre de la categoría está disponible
     */
    public function validateNombre(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:255'],
            'exclude_id' => ['sometimes', 'integer', 'exists:categorias,id']
        ]);

        if ($validator->fails()) {
            return response()->json(['available' => false, 'errors' => $validator->errors()], 422);
        }

        $nombre = $request->input('nombre');
        $excludeId = $request->input('exclude_id');

        $query = Categoria::where('nombre', $nombre);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $isAvailable = !$query->exists();

        return response()->json(['available' => $isAvailable]);
    }

    /**
     * Crear nueva categoría
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255|unique:categorias,nombre',
                'descripcion' => 'nullable|string|max:1000',
                'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
                'activo' => 'required|boolean',
                'orden' => 'required|integer|min:0',
                'categoria_padre_id' => 'nullable|integer|exists:categorias,id',
                'meta_title' => 'nullable|string|max:60',
                'meta_description' => 'nullable|string|max:160',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $data = $validator->validated();

            // Generar slug único
            $data['slug'] = Str::slug($data['nombre']);
            $originalSlug = $data['slug'];
            $counter = 1;

            while (Categoria::where('slug', $data['slug'])->exists()) {
                $data['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }

            // Validar jerarquía (evitar ciclos)
            if (isset($data['categoria_padre_id'])) {
                $this->validateHierarchy((int) $data['categoria_padre_id']);
            }

            // Manejar subida de imagen si existe
            if ($request->hasFile('imagen')) {
                $data['imagen'] = $this->handleImageUpload($request->file('imagen'));
            }

            $categoria = Categoria::create($data);

            DB::commit();

            // Limpiar cache
            Cache::forget('categorias_tree');

            Log::info("Categoría creada: {$categoria->id}", [
                'nombre' => $categoria->nombre,
                'imagen' => $categoria->imagen
            ]);

            return response()->json([
                'message' => 'Categoría creada exitosamente',
                'data' => new CategoriaResource($categoria->load(['categoriaPadre', 'subcategorias']))
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al crear categoría: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al crear la categoría.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar categoría existente usando POST
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $categoria = Categoria::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nombre' => ['required', 'string', 'max:255', Rule::unique('categorias')->ignore($id)],
                'descripcion' => 'nullable|string|max:1000',
                'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
                'activo' => 'required|boolean',
                'orden' => 'required|integer|min:0',
                'categoria_padre_id' => 'nullable|integer|exists:categorias,id',
                'meta_title' => 'nullable|string|max:60',
                'meta_description' => 'nullable|string|max:160',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $data = $validator->validated();
            $imagenAnterior = $categoria->imagen;

            // Validar que no se asigne como padre a sí misma o a sus descendientes
            if (isset($data['categoria_padre_id']) && $data['categoria_padre_id'] !== null) {
                $newParentId = (int) $data['categoria_padre_id'];
                
                if ($newParentId === $categoria->id) {
                    throw new Exception('Una categoría no puede ser padre de sí misma.');
                }
                
                // Validar que el nuevo padre no es un descendiente de la categoría actual (evitar ciclos)
                if ($this->isDescendant($categoria->id, $newParentId)) {
                    throw new Exception('No se puede mover una categoría a una de sus propias subcategorías.');
                }

                // Validar la profundidad máxima de la nueva jerarquía
                $this->validateHierarchy($newParentId);
            }

            // Actualizar slug si cambió el nombre
            if ($data['nombre'] !== $categoria->nombre) {
                $data['slug'] = Str::slug($data['nombre']);
                $originalSlug = $data['slug'];
                $counter = 1;

                while (Categoria::where('slug', $data['slug'])->where('id', '!=', $id)->exists()) {
                    $data['slug'] = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }

            // Manejar subida de nueva imagen
            if ($request->hasFile('imagen')) {
                $data['imagen'] = $this->handleImageUpload($request->file('imagen'));
                
                // Eliminar imagen anterior si existe
                if ($imagenAnterior) {
                    $this->deleteImage($imagenAnterior);
                }
            }

            $categoria->update($data);

            DB::commit();

            // Limpiar cache
            Cache::forget('categorias_tree');

            Log::info("Categoría actualizada: {$categoria->id}", [
                'cambios' => array_keys($data),
                'metodo' => 'POST'
            ]);

            return response()->json([
                'message' => 'Categoría actualizada exitosamente',
                'data' => new CategoriaResource($categoria->load(['categoriaPadre', 'subcategorias']))
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar categoría ID {$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al actualizar la categoría.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar categoría
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $categoria = Categoria::findOrFail($id);

            // Verificar si tiene productos asociados
            if ($categoria->productos()->count() > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar la categoría porque tiene productos asociados',
                    'productos_count' => $categoria->productos()->count()
                ], 409); // Conflict
            }

            // Verificar si tiene subcategorías
            if ($categoria->subcategorias()->count() > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar la categoría porque tiene subcategorías asociadas',
                    'subcategorias_count' => $categoria->subcategorias()->count()
                ], 409); // Conflict
            }

            DB::beginTransaction();

            $imagenParaEliminar = $categoria->imagen;
            $categoriaId = $categoria->id;
            $categoriaNombre = $categoria->nombre;

            $categoria->delete();

            // Eliminar imagen si existe
            if ($imagenParaEliminar) {
                $this->deleteImage($imagenParaEliminar);
            }

            DB::commit();

            // Limpiar cache
            Cache::forget('categorias_tree');

            Log::info("Categoría eliminada: {$categoriaId}", [
                'nombre' => $categoriaNombre
            ]);

            return response()->json(null, 204);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar categoría ID {$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar la categoría.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar orden de categorías
     */
    public function updateOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'categorias' => 'required|array',
            'categorias.*.id' => 'required|exists:categorias,id',
            'categorias.*.orden' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->categorias as $categoriaData) {
            Categoria::where('id', $categoriaData['id'])
                ->update(['orden' => $categoriaData['orden']]);
        }

        // Limpiar cache
        Cache::forget('categorias_tree');

        return response()->json([
            'message' => 'Orden de categorías actualizado exitosamente'
        ]);
    }

    /**
     * Subir imagen para categoría
     */
    public function uploadImage(Request $request, string $id): JsonResponse
    {
        try {
            $categoria = Categoria::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'imagen' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Imagen inválida',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $imagenAnterior = $categoria->imagen;

            // Subir nueva imagen
            $nuevaImagen = $this->handleImageUpload($request->file('imagen'));

            $categoria->update(['imagen' => $nuevaImagen]);

            // Eliminar imagen anterior si existe
            if ($imagenAnterior) {
                $this->deleteImage($imagenAnterior);
            }

            DB::commit();

            // Limpiar cache
            Cache::forget('categorias_tree');

            Log::info("Imagen subida para categoría: {$categoria->id}");

            return response()->json([
                'message' => 'Imagen subida exitosamente',
                'data' => new CategoriaResource($categoria->load(['categoriaPadre', 'subcategorias']))
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al subir imagen para categoría ID {$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al subir la imagen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar imagen de categoría
     */
    public function removeImage(string $id): JsonResponse
    {
        try {
            $categoria = Categoria::findOrFail($id);

            if (!$categoria->imagen) {
                return response()->json([
                    'message' => 'La categoría no tiene imagen asociada.'
                ], 400);
            }

            DB::beginTransaction();

            $imagenParaEliminar = $categoria->imagen;
            $categoria->update(['imagen' => null]);

            $this->deleteImage($imagenParaEliminar);

            DB::commit();

            // Limpiar cache
            Cache::forget('categorias_tree');

            Log::info("Imagen eliminada de categoría: {$categoria->id}");

            return response()->json([
                'message' => 'Imagen eliminada exitosamente',
                'data' => new CategoriaResource($categoria->load(['categoriaPadre', 'subcategorias']))
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar imagen de categoría ID {$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar la imagen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar jerarquía para evitar ciclos
     */
    private function validateHierarchy(?int $parentId, int $depth = 0): void
    {
        if ($depth > 5) { // Máximo 5 niveles de profundidad
            throw new \Exception('Jerarquía demasiado profunda');
        }

        if ($parentId) {
            $parent = Categoria::find($parentId);
            if ($parent && $parent->categoria_padre_id) {
                $this->validateHierarchy($parent->categoria_padre_id, $depth + 1);
            }
        }
    }

    /**
     * Helper recursivo para construir la lista plana de categorías con niveles
     */
    private function buildCategoryList($categorias, &$lista, int $nivel = 0): void
    {
        // Eager load subcategories to avoid N+1 queries in the loop
        $categorias->loadMissing('subcategorias');

        foreach ($categorias as $categoria) {
            $lista[] = [
                'id' => $categoria->id,
                'nombre' => str_repeat('— ', $nivel) . $categoria->nombre,
                'nivel' => $nivel
            ];
            
            if ($categoria->subcategorias->isNotEmpty()) {
                $this->buildCategoryList($categoria->subcategorias, $lista, $nivel + 1);
            }
        }
    }

    /**
     * Verificar si una categoría es descendiente de otra
     */
    private function isDescendant(int $categoryId, int $potentialAncestorId): bool
    {
        $descendants = $this->getAllDescendants($categoryId);
        return in_array($potentialAncestorId, $descendants);
    }

    /**
     * Obtener todos los descendientes de una categoría
     */
    private function getAllDescendants(int $categoryId): array
    {
        $descendants = [];
        $children = Categoria::where('categoria_padre_id', $categoryId)->pluck('id')->toArray();
        
        foreach ($children as $childId) {
            $descendants[] = $childId;
            $descendants = array_merge($descendants, $this->getAllDescendants($childId));
        }
        
        return $descendants;
    }

    /**
     * Handle image upload
     */
    private function handleImageUpload($file): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = uniqid('categoria_') . '.' . $extension;
        
        // Directorio de destino
        $directory = 'assets/categorias';
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