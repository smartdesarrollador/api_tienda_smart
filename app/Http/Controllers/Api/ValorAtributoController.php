<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ValorAtributo;
use App\Models\Atributo;
use App\Http\Requests\StoreValorAtributoRequest;
use App\Http\Requests\UpdateValorAtributoRequest;
use App\Http\Resources\ValorAtributoResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Exception;

class ValorAtributoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) $request->query('per_page', 15), 100);
            $query = ValorAtributo::query();

            // Filtro por atributo específico
            if ($request->has('atributo_id')) {
                $query->where('atributo_id', $request->query('atributo_id'));
            }

            // Filtro por valor (búsqueda parcial)
            if ($request->has('valor')) {
                $query->where('valor', 'like', '%' . $request->query('valor') . '%');
            }

            // Filtro por código
            if ($request->has('codigo')) {
                $query->where('codigo', 'like', '%' . $request->query('codigo') . '%');
            }

            // Filtro por tipo de atributo
            if ($request->has('tipo_atributo')) {
                $query->whereHas('atributo', function ($q) use ($request) {
                    $q->where('tipo', $request->query('tipo_atributo'));
                });
            }

            // Filtro para valores con imagen
            if ($request->has('con_imagen')) {
                $conImagen = filter_var($request->query('con_imagen'), FILTER_VALIDATE_BOOLEAN);
                if ($conImagen) {
                    $query->whereNotNull('imagen');
                } else {
                    $query->whereNull('imagen');
                }
            }

            // Incluir conteo de variaciones que usan este valor
            if ($request->query('include_usage', false)) {
                $query->withCount('variaciones');
            }

            // Cargar relaciones
            $query->with(['atributo:id,nombre,tipo,slug']);

            // Ordenamiento
            $orderBy = $request->query('order_by', 'valor');
            $orderDirection = $request->query('order_direction', 'asc');
            
            if (in_array($orderBy, ['valor', 'codigo', 'created_at', 'atributo_id'])) {
                if ($orderBy === 'atributo_id') {
                    // Ordenar por nombre del atributo
                    $query->join('atributos', 'valores_atributo.atributo_id', '=', 'atributos.id')
                          ->orderBy('atributos.nombre', $orderDirection)
                          ->orderBy('valores_atributo.valor', 'asc')
                          ->select('valores_atributo.*');
                } else {
                    $query->orderBy($orderBy, $orderDirection);
                }
            } else {
                $query->orderBy('valor', 'asc');
            }

            $valoresAtributo = $query->paginate($perPage);
            
            return ValorAtributoResource::collection($valoresAtributo)
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al obtener valores de atributo: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener valores de atributo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreValorAtributoRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosValidados = $request->validated();
            
            // Manejar subida de imagen si existe
            if ($request->hasFile('imagen')) {
                $datosValidados['imagen'] = $this->handleImageUpload($request->file('imagen'));
            }

            $valorAtributo = ValorAtributo::create($datosValidados);
            
            DB::commit();

            Log::info("Valor de atributo creado: {$valorAtributo->id}", [
                'atributo_id' => $valorAtributo->atributo_id,
                'valor' => $valorAtributo->valor
            ]);

            return (new ValorAtributoResource($valorAtributo->load('atributo')))
                ->response()
                ->setStatusCode(201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al crear valor de atributo: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al crear el valor de atributo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ValorAtributo $valorAtributo): JsonResponse
    {
        try {
            $valorAtributo->load(['atributo']);
            
            return (new ValorAtributoResource($valorAtributo))
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al obtener valor de atributo ID {$valorAtributo->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener el valor de atributo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateValorAtributoRequest $request, ValorAtributo $valorAtributo): JsonResponse
    {
        try {
            DB::beginTransaction();

            $datosValidados = $request->validated();
            $imagenAnterior = $valorAtributo->imagen;
            
            // Manejar subida de nueva imagen
            if ($request->hasFile('imagen')) {
                $datosValidados['imagen'] = $this->handleImageUpload($request->file('imagen'));
                
                // Eliminar imagen anterior si existe
                if ($imagenAnterior) {
                    $this->deleteImage($imagenAnterior);
                }
            }

            $valorAtributo->update($datosValidados);
            
            DB::commit();

            Log::info("Valor de atributo actualizado: {$valorAtributo->id}", [
                'cambios' => array_keys($datosValidados)
            ]);

            return (new ValorAtributoResource($valorAtributo->load('atributo')))
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar valor de atributo ID {$valorAtributo->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al actualizar el valor de atributo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ValorAtributo $valorAtributo): JsonResponse
    {
        try {
            // Verificar si el valor está siendo usado por variaciones de productos
            if ($valorAtributo->variaciones()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar el valor de atributo porque está siendo usado por productos.',
                    'variaciones_count' => $valorAtributo->variaciones()->count()
                ], 409); // Conflict
            }

            DB::beginTransaction();

            $imagenParaEliminar = $valorAtributo->imagen;
            $valorId = $valorAtributo->id;
            $valorTexto = $valorAtributo->valor;

            $valorAtributo->delete();

            // Eliminar imagen si existe
            if ($imagenParaEliminar) {
                $this->deleteImage($imagenParaEliminar);
            }

            DB::commit();

            Log::info("Valor de atributo eliminado: {$valorId}", [
                'valor' => $valorTexto
            ]);

            return response()->json(null, 204);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar valor de atributo ID {$valorAtributo->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar el valor de atributo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get valores by atributo (endpoint específico para facilitar uso desde frontend)
     */
    public function byAtributo(Atributo $atributo): JsonResponse
    {
        try {
            $valores = $atributo->valores()
                ->orderBy('valor', 'asc')
                ->get();

            return ValorAtributoResource::collection($valores)
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al obtener valores del atributo {$atributo->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener valores del atributo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create valores for an atributo
     */
    public function bulkStore(Request $request, Atributo $atributo): JsonResponse
    {
        $request->validate([
            'valores' => 'required|array|min:1|max:50',
            'valores.*.valor' => 'required|string|max:255',
            'valores.*.codigo' => 'nullable|string|max:50',
        ]);

        try {
            DB::beginTransaction();

            $valoresCreados = [];
            $errores = [];

            foreach ($request->valores as $index => $valorData) {
                try {
                    // Verificar que no exista el valor para este atributo
                    $existe = ValorAtributo::where('atributo_id', $atributo->id)
                        ->where('valor', $valorData['valor'])
                        ->exists();

                    if ($existe) {
                        $errores[] = "El valor '{$valorData['valor']}' ya existe para este atributo";
                        continue;
                    }

                    $valorAtributo = ValorAtributo::create([
                        'atributo_id' => $atributo->id,
                        'valor' => trim($valorData['valor']),
                        'codigo' => isset($valorData['codigo']) ? trim($valorData['codigo']) : null,
                    ]);

                    $valoresCreados[] = $valorAtributo;
                } catch (Exception $e) {
                    $errores[] = "Error al crear valor '{$valorData['valor']}': " . $e->getMessage();
                }
            }

            DB::commit();

            Log::info("Creación masiva de valores para atributo {$atributo->id}", [
                'creados' => count($valoresCreados),
                'errores' => count($errores)
            ]);

            return response()->json([
                'message' => 'Proceso de creación masiva completado',
                'creados' => ValorAtributoResource::collection($valoresCreados),
                'errores' => $errores,
                'total_creados' => count($valoresCreados),
                'total_errores' => count($errores)
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error en creación masiva de valores: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor en la creación masiva.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove imagen from valor
     */
    public function removeImage(ValorAtributo $valorAtributo): JsonResponse
    {
        try {
            if (!$valorAtributo->imagen) {
                return response()->json([
                    'message' => 'El valor de atributo no tiene imagen asociada.'
                ], 400);
            }

            DB::beginTransaction();

            $imagenParaEliminar = $valorAtributo->imagen;
            $valorAtributo->update(['imagen' => null]);

            $this->deleteImage($imagenParaEliminar);

            DB::commit();

            Log::info("Imagen eliminada del valor de atributo: {$valorAtributo->id}");

            return (new ValorAtributoResource($valorAtributo->load('atributo')))
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar imagen del valor de atributo {$valorAtributo->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al eliminar la imagen.',
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
        $filename = uniqid('valor_attr_') . '.' . $extension;
        $path = $file->storeAs('valores-atributo', $filename, 'public');
        
        return $path;
    }

    /**
     * Delete image from storage
     */
    private function deleteImage(string $imagePath): bool
    {
        try {
            if (Storage::disk('public')->exists($imagePath)) {
                return Storage::disk('public')->delete($imagePath);
            }
            return true;
        } catch (Exception $e) {
            Log::warning("No se pudo eliminar imagen: {$imagePath}. Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get statistics for valores de atributo
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_valores' => ValorAtributo::count(),
                'valores_con_imagen' => ValorAtributo::whereNotNull('imagen')->count(),
                'valores_con_codigo' => ValorAtributo::whereNotNull('codigo')->count(),
                'valores_en_uso' => ValorAtributo::has('variaciones')->count(),
                'por_tipo_atributo' => ValorAtributo::join('atributos', 'valores_atributo.atributo_id', '=', 'atributos.id')
                    ->select('atributos.tipo', DB::raw('count(*) as total'))
                    ->groupBy('atributos.tipo')
                    ->get()
                    ->pluck('total', 'tipo'),
                'top_atributos' => ValorAtributo::join('atributos', 'valores_atributo.atributo_id', '=', 'atributos.id')
                    ->select('atributos.nombre', DB::raw('count(*) as total_valores'))
                    ->groupBy('atributos.id', 'atributos.nombre')
                    ->orderByDesc('total_valores')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'data' => $stats
            ], 200);
        } catch (Exception $e) {
            Log::error("Error al obtener estadísticas de valores de atributo: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor al obtener estadísticas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 