<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\SeoProductoResource;
use App\Models\SeoProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SeoProductoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $seoProductos = SeoProducto::query()
            ->with(['producto'])
            ->when($request->producto_id, fn($q) => $q->where('producto_id', $request->producto_id))
            ->when($request->search, function($q) use ($request) {
                return $q->where('meta_title', 'like', '%' . $request->search . '%')
                        ->orWhere('meta_description', 'like', '%' . $request->search . '%')
                        ->orWhere('meta_keywords', 'like', '%' . $request->search . '%');
            })
            ->when($request->sin_meta_title, fn($q) => $q->whereNull('meta_title'))
            ->when($request->sin_meta_description, fn($q) => $q->whereNull('meta_description'))
            ->when($request->optimizado, function($q) use ($request) {
                if ($request->optimizado === 'true') {
                    return $q->whereNotNull('meta_title')
                            ->whereNotNull('meta_description')
                            ->whereNotNull('canonical_url');
                } else {
                    return $q->where(function($query) {
                        $query->whereNull('meta_title')
                              ->orWhereNull('meta_description')
                              ->orWhereNull('canonical_url');
                    });
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => SeoProductoResource::collection($seoProductos),
            'meta' => [
                'total' => $seoProductos->total(),
                'per_page' => $seoProductos->perPage(),
                'current_page' => $seoProductos->currentPage(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'producto_id' => 'required|exists:productos,id|unique:seo_productos,producto_id',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'meta_keywords' => 'nullable|string|max:255',
            'canonical_url' => 'nullable|string|max:255',
            'schema_markup' => 'nullable|json',
            'og_title' => 'nullable|string|max:60',
            'og_description' => 'nullable|string|max:160',
            'og_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $seoProducto = SeoProducto::create([
                'producto_id' => $request->producto_id,
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords' => $request->meta_keywords,
                'canonical_url' => $request->canonical_url,
                'schema_markup' => $request->schema_markup,
                'og_title' => $request->og_title,
                'og_description' => $request->og_description,
            ]);

            if ($request->hasFile('og_image')) {
                $imagen = $request->file('og_image');
                $nombreImagen = time() . '_' . $imagen->getClientOriginalName();
                $imagen->move(public_path('assets/seo'), $nombreImagen);
                $seoProducto->og_image = $nombreImagen;
                $seoProducto->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Configuración SEO creada exitosamente',
                'data' => new SeoProductoResource($seoProducto->load(['producto']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la configuración SEO'], 500);
        }
    }

    public function show(SeoProducto $seoProducto): JsonResponse
    {
        return response()->json([
            'data' => new SeoProductoResource($seoProducto->load(['producto']))
        ]);
    }

    public function update(Request $request, SeoProducto $seoProducto): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'meta_keywords' => 'nullable|string|max:255',
            'canonical_url' => 'nullable|string|max:255',
            'schema_markup' => 'nullable|json',
            'og_title' => 'nullable|string|max:60',
            'og_description' => 'nullable|string|max:160',
            'og_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $seoProducto->fill($request->except(['og_image']));

            if ($request->hasFile('og_image')) {
                // Eliminar imagen anterior si existe
                if ($seoProducto->og_image && file_exists(public_path('assets/seo/' . $seoProducto->og_image))) {
                    unlink(public_path('assets/seo/' . $seoProducto->og_image));
                }

                $imagen = $request->file('og_image');
                $nombreImagen = time() . '_' . $imagen->getClientOriginalName();
                $imagen->move(public_path('assets/seo'), $nombreImagen);
                $seoProducto->og_image = $nombreImagen;
            }

            $seoProducto->save();
            DB::commit();

            return response()->json([
                'message' => 'Configuración SEO actualizada exitosamente',
                'data' => new SeoProductoResource($seoProducto->load(['producto']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar la configuración SEO'], 500);
        }
    }

    public function destroy(SeoProducto $seoProducto): JsonResponse
    {
        try {
            // Eliminar imagen si existe
            if ($seoProducto->og_image && file_exists(public_path('assets/seo/' . $seoProducto->og_image))) {
                unlink(public_path('assets/seo/' . $seoProducto->og_image));
            }

            $seoProducto->delete();
            return response()->json(['message' => 'Configuración SEO eliminada exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la configuración SEO'], 500);
        }
    }

    public function generarAutomatico(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'producto_id' => 'required|exists:productos,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $producto = \App\Models\Producto::findOrFail($request->producto_id);
            
            // Verificar si ya existe configuración SEO
            $seoExistente = SeoProducto::where('producto_id', $producto->id)->first();
            if ($seoExistente) {
                return response()->json(['message' => 'Ya existe configuración SEO para este producto'], 400);
            }

            $seoGenerado = $this->generarSeoAutomatico($producto);

            $seoProducto = SeoProducto::create($seoGenerado);

            return response()->json([
                'message' => 'Configuración SEO generada automáticamente',
                'data' => new SeoProductoResource($seoProducto->load(['producto']))
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al generar la configuración SEO automática'], 500);
        }
    }

    public function analizarSeo(SeoProducto $seoProducto): JsonResponse
    {
        try {
            $analisis = [
                'puntuacion_general' => $this->calcularPuntuacionSeo($seoProducto),
                'elementos_optimizados' => $this->getElementosOptimizados($seoProducto),
                'elementos_faltantes' => $this->getElementosFaltantes($seoProducto),
                'recomendaciones_prioritarias' => $this->getRecomendacionesPrioritarias($seoProducto),
                'comparacion_competencia' => $this->getComparacionCompetencia($seoProducto),
            ];

            return response()->json([
                'analisis' => $analisis,
                'seo_producto' => new SeoProductoResource($seoProducto->load(['producto']))
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al analizar la configuración SEO'], 500);
        }
    }

    public function optimizarMasivo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'productos_ids' => 'required|array',
            'productos_ids.*' => 'exists:productos,id',
            'sobrescribir' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $productosOptimizados = 0;
            $productosOmitidos = 0;

            foreach ($request->productos_ids as $productoId) {
                $seoExistente = SeoProducto::where('producto_id', $productoId)->first();
                
                if ($seoExistente && !$request->sobrescribir) {
                    $productosOmitidos++;
                    continue;
                }

                $producto = \App\Models\Producto::find($productoId);
                if (!$producto) {
                    continue;
                }

                $seoGenerado = $this->generarSeoAutomatico($producto);

                if ($seoExistente) {
                    $seoExistente->update($seoGenerado);
                } else {
                    SeoProducto::create($seoGenerado);
                }

                $productosOptimizados++;
            }

            DB::commit();

            return response()->json([
                'message' => 'Optimización masiva completada',
                'productos_optimizados' => $productosOptimizados,
                'productos_omitidos' => $productosOmitidos,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error en la optimización masiva'], 500);
        }
    }

    private function generarSeoAutomatico($producto): array
    {
        $metaTitle = $this->generarMetaTitle($producto);
        $metaDescription = $this->generarMetaDescription($producto);
        $keywords = $this->generarKeywords($producto);
        $canonicalUrl = $this->generarCanonicalUrl($producto);
        $schemaMarkup = $this->generarSchemaMarkup($producto);

        return [
            'producto_id' => $producto->id,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $keywords,
            'canonical_url' => $canonicalUrl,
            'schema_markup' => $schemaMarkup,
            'og_title' => $metaTitle,
            'og_description' => $metaDescription,
        ];
    }

    private function generarMetaTitle($producto): string
    {
        $titulo = $producto->nombre;
        if (strlen($titulo) > 55) {
            $titulo = substr($titulo, 0, 52) . '...';
        }
        return $titulo . ' | Tienda Virtual';
    }

    private function generarMetaDescription($producto): string
    {
        $descripcion = $producto->descripcion ?? $producto->nombre;
        $precio = number_format($producto->precio, 2);
        
        $metaDesc = "Compra {$producto->nombre} por S/ {$precio}. ";
        $metaDesc .= substr(strip_tags($descripcion), 0, 120);
        
        if (strlen($metaDesc) > 155) {
            $metaDesc = substr($metaDesc, 0, 152) . '...';
        }
        
        return $metaDesc;
    }

    private function generarKeywords($producto): string
    {
        $keywords = [$producto->nombre];
        
        // Agregar categoría si existe
        if ($producto->categoria) {
            $keywords[] = $producto->categoria->nombre;
        }
        
        // Agregar palabras del nombre del producto
        $palabras = explode(' ', $producto->nombre);
        foreach ($palabras as $palabra) {
            if (strlen($palabra) > 3) {
                $keywords[] = strtolower($palabra);
            }
        }
        
        return implode(', ', array_unique($keywords));
    }

    private function generarCanonicalUrl($producto): string
    {
        return url("/productos/{$producto->slug}");
    }

    private function generarSchemaMarkup($producto): array
    {
        return [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $producto->nombre,
            'description' => strip_tags($producto->descripcion ?? $producto->nombre),
            'sku' => $producto->sku,
            'offers' => [
                '@type' => 'Offer',
                'price' => $producto->precio,
                'priceCurrency' => 'PEN',
                'availability' => $producto->disponible ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ],
        ];
    }

    private function calcularPuntuacionSeo(SeoProducto $seoProducto): int
    {
        $puntuacion = 0;
        
        if ($seoProducto->esMetaTitleValido()) $puntuacion += 20;
        if ($seoProducto->esMetaDescriptionValida()) $puntuacion += 20;
        if ($seoProducto->tieneKeywords()) $puntuacion += 15;
        if ($seoProducto->tieneCanonical()) $puntuacion += 15;
        if ($seoProducto->tieneOgTags()) $puntuacion += 15;
        if ($seoProducto->esSchemaMarkupValido()) $puntuacion += 15;
        
        return $puntuacion;
    }

    private function getElementosOptimizados(SeoProducto $seoProducto): array
    {
        $optimizados = [];
        
        if ($seoProducto->esMetaTitleValido()) $optimizados[] = 'Meta Title';
        if ($seoProducto->esMetaDescriptionValida()) $optimizados[] = 'Meta Description';
        if ($seoProducto->tieneKeywords()) $optimizados[] = 'Keywords';
        if ($seoProducto->tieneCanonical()) $optimizados[] = 'URL Canónica';
        if ($seoProducto->tieneOgTags()) $optimizados[] = 'Open Graph';
        if ($seoProducto->esSchemaMarkupValido()) $optimizados[] = 'Schema Markup';
        
        return $optimizados;
    }

    private function getElementosFaltantes(SeoProducto $seoProducto): array
    {
        $faltantes = [];
        
        if (!$seoProducto->esMetaTitleValido()) $faltantes[] = 'Meta Title';
        if (!$seoProducto->esMetaDescriptionValida()) $faltantes[] = 'Meta Description';
        if (!$seoProducto->tieneKeywords()) $faltantes[] = 'Keywords';
        if (!$seoProducto->tieneCanonical()) $faltantes[] = 'URL Canónica';
        if (!$seoProducto->tieneOgTags()) $faltantes[] = 'Open Graph';
        if (!$seoProducto->esSchemaMarkupValido()) $faltantes[] = 'Schema Markup';
        
        return $faltantes;
    }

    private function getRecomendacionesPrioritarias(SeoProducto $seoProducto): array
    {
        $recomendaciones = [];
        
        if (!$seoProducto->esMetaTitleValido()) {
            $recomendaciones[] = [
                'prioridad' => 'alta',
                'elemento' => 'Meta Title',
                'accion' => 'Crear un título optimizado de 30-60 caracteres'
            ];
        }
        
        if (!$seoProducto->esMetaDescriptionValida()) {
            $recomendaciones[] = [
                'prioridad' => 'alta',
                'elemento' => 'Meta Description',
                'accion' => 'Escribir una descripción atractiva de 120-160 caracteres'
            ];
        }
        
        if (!$seoProducto->tieneCanonical()) {
            $recomendaciones[] = [
                'prioridad' => 'media',
                'elemento' => 'URL Canónica',
                'accion' => 'Definir la URL canónica del producto'
            ];
        }
        
        return $recomendaciones;
    }

    private function getComparacionCompetencia(SeoProducto $seoProducto): array
    {
        // Simulación de comparación con competencia
        return [
            'posicion_estimada' => rand(1, 10),
            'competidores_analizados' => 5,
            'puntuacion_promedio_competencia' => rand(60, 85),
            'areas_mejora' => ['Velocidad de carga', 'Contenido multimedia', 'Enlaces internos']
        ];
    }
} 