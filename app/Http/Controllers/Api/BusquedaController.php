<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class BusquedaController extends Controller
{
    /**
     * Búsqueda general de productos
     */
    public function buscar(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'q' => 'required|string|min:2|max:255',
                'categoria' => 'nullable|exists:categorias,id',
                'precio_min' => 'nullable|numeric|min:0',
                'precio_max' => 'nullable|numeric|min:0',
                'marca' => 'nullable|string|max:100',
                'ordenar_por' => 'nullable|in:relevancia,precio_asc,precio_desc,nombre,fecha,popularidad',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:50'
            ]);

            $termino = $validated['q'];
            $perPage = $validated['per_page'] ?? 12;
            
            // Construir query base
            $query = Producto::query()
                ->where('activo', true)
                ->where(function($q) use ($termino) {
                    $q->where('nombre', 'like', "%{$termino}%")
                      ->orWhere('descripcion', 'like', "%{$termino}%")
                      ->orWhere('sku', 'like', "%{$termino}%")
                      ->orWhere('marca', 'like', "%{$termino}%")
                      ->orWhere('modelo', 'like', "%{$termino}%");
                });

            // Aplicar filtros adicionales
            if ($validated['categoria'] ?? null) {
                $query->where('categoria_id', $validated['categoria']);
            }

            if ($validated['precio_min'] ?? null) {
                $query->where('precio', '>=', $validated['precio_min']);
            }

            if ($validated['precio_max'] ?? null) {
                $query->where('precio', '<=', $validated['precio_max']);
            }

            if ($validated['marca'] ?? null) {
                $query->where('marca', 'like', "%{$validated['marca']}%");
            }

            // Aplicar ordenamiento
            $this->aplicarOrdenamiento($query, $validated['ordenar_por'] ?? 'relevancia', $termino);

            // Cargar relaciones
            $query->with(['categoria:id,nombre,slug']);

            $productos = $query->paginate($perPage);

            // Registrar búsqueda para estadísticas
            $this->registrarBusqueda($termino, $productos->total(), $request->ip());

            // Obtener sugerencias relacionadas
            $sugerencias = $this->obtenerSugerencias($termino);

            // Transformar productos para incluir URLs completas de imagen
            $productosTransformados = collect($productos->items())->map(function ($producto) {
                $data = $producto->toArray();
                $data['imagen_principal'] = $producto->imagen_principal 
                    ? url($producto->imagen_principal) 
                    : url('/assets/productos/default.jpg');
                return $data;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'productos' => $productosTransformados,
                    'pagination' => [
                        'current_page' => $productos->currentPage(),
                        'last_page' => $productos->lastPage(),
                        'per_page' => $productos->perPage(),
                        'total' => $productos->total()
                    ],
                    'termino_busqueda' => $termino,
                    'total_resultados' => $productos->total(),
                    'sugerencias' => $sugerencias,
                    'tiempo_busqueda' => round((microtime(true) - LARAVEL_START) * 1000, 2) . 'ms'
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error en búsqueda de productos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Búsqueda avanzada con múltiples filtros
     */
    public function busquedaAvanzada(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'termino' => 'nullable|string|max:255',
                'categorias' => 'nullable|array',
                'categorias.*' => 'exists:categorias,id',
                'precio_min' => 'nullable|numeric|min:0',
                'precio_max' => 'nullable|numeric|min:0',
                'marcas' => 'nullable|array',
                'marcas.*' => 'string|max:100',
                'con_descuento' => 'nullable|boolean',
                'en_stock' => 'nullable|boolean',
                'calificacion_min' => 'nullable|numeric|min:1|max:5',
                'atributos' => 'nullable|array',
                'ordenar_por' => 'nullable|in:relevancia,precio_asc,precio_desc,nombre,fecha,popularidad,calificacion',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:50'
            ]);

            $perPage = $validated['per_page'] ?? 12;
            
            $query = Producto::query()->where('activo', true);

            // Búsqueda por término
            if ($validated['termino'] ?? null) {
                $termino = $validated['termino'];
                $query->where(function($q) use ($termino) {
                    $q->where('nombre', 'like', "%{$termino}%")
                      ->orWhere('descripcion', 'like', "%{$termino}%")
                      ->orWhere('sku', 'like', "%{$termino}%")
                      ->orWhere('marca', 'like', "%{$termino}%");
                });
            }

            // Filtro por categorías
            if ($validated['categorias'] ?? null) {
                $query->whereIn('categoria_id', $validated['categorias']);
            }

            // Filtro por rango de precios
            if ($validated['precio_min'] ?? null) {
                $query->where('precio', '>=', $validated['precio_min']);
            }
            if ($validated['precio_max'] ?? null) {
                $query->where('precio', '<=', $validated['precio_max']);
            }

            // Filtro por marcas
            if ($validated['marcas'] ?? null) {
                $query->whereIn('marca', $validated['marcas']);
            }

            // Filtro por productos con descuento
            if ($validated['con_descuento'] ?? null) {
                $query->whereNotNull('precio_oferta')
                     ->where('precio_oferta', '<', DB::raw('precio'));
            }

            // Filtro por stock
            if ($validated['en_stock'] ?? null) {
                $query->where('stock', '>', 0);
            }

            // Filtro por calificación mínima
            if ($validated['calificacion_min'] ?? null) {
                $query->whereHas('comentarios', function($q) use ($validated) {
                    $q->selectRaw('AVG(calificacion) as promedio_calificacion')
                      ->havingRaw('AVG(calificacion) >= ?', [$validated['calificacion_min']]);
                });
            }

            // Aplicar ordenamiento
            $this->aplicarOrdenamiento($query, $validated['ordenar_por'] ?? 'relevancia');

            // Cargar relaciones con conteos y promedios
            $query->with([
                'categoria:id,nombre,slug',
                'imagenes:id,producto_id,url,alt,principal'
            ])
            ->withCount('comentarios')
            ->withAvg('comentarios', 'calificacion');

            $productos = $query->paginate($perPage);

            // Transformar productos para incluir URLs completas de imagen
            $productosTransformados = collect($productos->items())->map(function ($producto) {
                $data = $producto->toArray();
                $data['imagen_principal'] = $producto->imagen_principal 
                    ? url($producto->imagen_principal) 
                    : url('/assets/productos/default.jpg');
                return $data;
            });

            // Obtener filtros disponibles basados en los resultados
            $filtrosDisponibles = $this->obtenerFiltrosDisponibles($productos->items());

            return response()->json([
                'success' => true,
                'data' => [
                    'productos' => $productosTransformados,
                    'pagination' => [
                        'current_page' => $productos->currentPage(),
                        'last_page' => $productos->lastPage(),
                        'per_page' => $productos->perPage(),
                        'total' => $productos->total()
                    ],
                    'filtros_aplicados' => $validated,
                    'filtros_disponibles' => $filtrosDisponibles,
                    'total_resultados' => $productos->total()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error en búsqueda avanzada: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Autocompletado de búsqueda
     */
    public function autocompletar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2|max:100'
            ]);

            $termino = $request->query('q');
            
            // Búsqueda en productos
            $productos = Producto::where('activo', true)
                ->where(function($query) use ($termino) {
                    $query->where('nombre', 'like', "{$termino}%")
                          ->orWhere('marca', 'like', "{$termino}%");
                })
                ->select('id', 'nombre', 'marca', 'precio', 'imagen_principal')
                ->limit(5)
                ->get()
                ->map(function ($producto) {
                    return [
                        'id' => $producto->id,
                        'nombre' => $producto->nombre,
                        'marca' => $producto->marca,
                        'precio' => $producto->precio,
                        'imagen_principal' => $producto->imagen_principal ? url($producto->imagen_principal) : url('/assets/productos/default.jpg'),
                    ];
                });

            // Búsqueda en categorías
            $categorias = Categoria::where('activo', true)
                ->where('nombre', 'like', "{$termino}%")
                ->select('id', 'nombre', 'slug')
                ->limit(3)
                ->get();

            // Sugerencias de marcas
            $marcas = Producto::where('activo', true)
                ->where('marca', 'like', "{$termino}%")
                ->select('marca')
                ->distinct()
                ->limit(3)
                ->pluck('marca');

            // Búsquedas populares relacionadas
            $busquedasPopulares = $this->obtenerBusquedasPopulares($termino);

            return response()->json([
                'success' => true,
                'data' => [
                    'productos' => $productos,
                    'categorias' => $categorias,
                    'marcas' => $marcas,
                    'busquedas_populares' => $busquedasPopulares,
                    'termino' => $termino
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error en autocompletado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener términos de búsqueda populares
     */
    public function terminosPopulares(): JsonResponse
    {
        try {
            // En una implementación real, esto vendría de una tabla de estadísticas
            $terminosPopulares = [
                ['termino' => 'smartphone', 'count' => 1245],
                ['termino' => 'laptop', 'count' => 987],
                ['termino' => 'auriculares', 'count' => 756],
                ['termino' => 'tablet', 'count' => 654],
                ['termino' => 'cámara', 'count' => 543],
                ['termino' => 'smartwatch', 'count' => 432],
                ['termino' => 'gaming', 'count' => 398],
                ['termino' => 'bluetooth', 'count' => 321],
                ['termino' => 'monitor', 'count' => 287],
                ['termino' => 'teclado', 'count' => 245]
            ];

            return response()->json([
                'success' => true,
                'data' => $terminosPopulares
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener términos populares: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener filtros de búsqueda disponibles
     */
    public function filtrosDisponibles(): JsonResponse
    {
        try {
            // Categorías activas
            $categorias = Categoria::where('activo', true)
                ->select('id', 'nombre', 'slug')
                ->withCount(['productos' => function($query) {
                    $query->where('activo', true);
                }])
                ->having('productos_count', '>', 0)
                ->orderBy('nombre')
                ->get();

            // Marcas disponibles
            $marcas = Producto::where('activo', true)
                ->select('marca')
                ->selectRaw('COUNT(*) as productos_count')
                ->groupBy('marca')
                ->orderBy('productos_count', 'desc')
                ->limit(20)
                ->get();

            // Rangos de precio
            $rangosPrecios = [
                ['min' => 0, 'max' => 50, 'label' => 'Menos de S/50'],
                ['min' => 50, 'max' => 100, 'label' => 'S/50 - S/100'],
                ['min' => 100, 'max' => 200, 'label' => 'S/100 - S/200'],
                ['min' => 200, 'max' => 500, 'label' => 'S/200 - S/500'],
                ['min' => 500, 'max' => 1000, 'label' => 'S/500 - S/1,000'],
                ['min' => 1000, 'max' => null, 'label' => 'Más de S/1,000']
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'categorias' => $categorias,
                    'marcas' => $marcas,
                    'rangos_precios' => $rangosPrecios,
                    'opciones_ordenamiento' => [
                        ['value' => 'relevancia', 'label' => 'Relevancia'],
                        ['value' => 'precio_asc', 'label' => 'Precio: Menor a Mayor'],
                        ['value' => 'precio_desc', 'label' => 'Precio: Mayor a Menor'],
                        ['value' => 'nombre', 'label' => 'Nombre A-Z'],
                        ['value' => 'fecha', 'label' => 'Más Recientes'],
                        ['value' => 'popularidad', 'label' => 'Más Populares'],
                        ['value' => 'calificacion', 'label' => 'Mejor Calificados']
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener filtros disponibles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Estadísticas de búsqueda
     */
    public function estadisticas(): JsonResponse
    {
        try {
            // En una implementación real, esto vendría de tablas de estadísticas
            $estadisticas = [
                'total_busquedas_hoy' => 2456,
                'total_busquedas_mes' => 78923,
                'promedio_resultados' => 12.4,
                'terminos_sin_resultados' => 234,
                'tasa_conversion_busqueda' => 18.7,
                'top_terminos_hoy' => [
                    ['termino' => 'iphone', 'busquedas' => 145],
                    ['termino' => 'laptop gaming', 'busquedas' => 123],
                    ['termino' => 'auriculares bluetooth', 'busquedas' => 98]
                ],
                'categorias_mas_buscadas' => [
                    ['categoria' => 'Electrónicos', 'porcentaje' => 35.2],
                    ['categoria' => 'Informática', 'porcentaje' => 28.7],
                    ['categoria' => 'Hogar', 'porcentaje' => 15.3]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $estadisticas
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas de búsqueda: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Aplicar ordenamiento a la consulta
     */
    private function aplicarOrdenamiento($query, string $ordenarPor, ?string $termino = null): void
    {
        switch ($ordenarPor) {
            case 'precio_asc':
                $query->orderBy('precio', 'asc');
                break;
            case 'precio_desc':
                $query->orderBy('precio', 'desc');
                break;
            case 'nombre':
                $query->orderBy('nombre', 'asc');
                break;
            case 'fecha':
                $query->orderBy('created_at', 'desc');
                break;
            case 'popularidad':
                $query->orderBy('vistas', 'desc');
                break;
            case 'calificacion':
                $query->orderBy('promedio_calificacion', 'desc');
                break;
            case 'relevancia':
            default:
                if ($termino) {
                    // Ordenar por relevancia basada en coincidencias exactas primero
                    $query->orderByRaw("CASE WHEN nombre LIKE '{$termino}%' THEN 1 ELSE 2 END")
                          ->orderByRaw("CASE WHEN nombre LIKE '%{$termino}%' THEN 1 ELSE 2 END")
                          ->orderBy('nombre', 'asc');
                } else {
                    $query->orderBy('nombre', 'asc');
                }
                break;
        }
    }

    /**
     * Registrar búsqueda para estadísticas
     */
    private function registrarBusqueda(string $termino, int $resultados, string $ip): void
    {
        // En una implementación real, esto se guardaría en una tabla de estadísticas
        Log::info('Búsqueda registrada', [
            'termino' => $termino,
            'resultados' => $resultados,
            'ip' => $ip,
            'fecha' => now()->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obtener sugerencias relacionadas
     */
    private function obtenerSugerencias(string $termino): array
    {
        // En una implementación real, esto podría usar algoritmos más sofisticados
        $sugerencias = [];
        
        // Sugerencias basadas en correcciones ortográficas simples
        if (str_contains($termino, 'telefono')) {
            $sugerencias[] = str_replace('telefono', 'teléfono', $termino);
        }
        
        // Sugerencias de términos relacionados
        $relacionados = [
            'smartphone' => ['teléfono', 'celular', 'móvil'],
            'laptop' => ['portátil', 'notebook', 'computadora'],
            'auriculares' => ['headphones', 'audífonos', 'cascos']
        ];
        
        foreach ($relacionados as $clave => $valores) {
            if (str_contains(strtolower($termino), $clave)) {
                $sugerencias = array_merge($sugerencias, $valores);
            }
        }
        
        return array_unique($sugerencias);
    }

    /**
     * Obtener filtros disponibles basados en resultados
     */
    private function obtenerFiltrosDisponibles($productos): array
    {
        $marcas = $productos->pluck('marca')->unique()->filter()->values();
        $categorias = $productos->pluck('categoria.nombre')->unique()->filter()->values();
        
        return [
            'marcas_disponibles' => $marcas,
            'categorias_disponibles' => $categorias,
            'rango_precios' => [
                'min' => $productos->min('precio') ?? 0,
                'max' => $productos->max('precio') ?? 0
            ]
        ];
    }

    /**
     * Obtener búsquedas populares relacionadas
     */
    private function obtenerBusquedasPopulares(string $termino): array
    {
        // En una implementación real, esto consultaría estadísticas de búsqueda
        $populares = [
            'smartphone samsung',
            'laptop gaming',
            'auriculares bluetooth',
            'tablet android',
            'smartwatch apple'
        ];
        
        return array_filter($populares, function($busqueda) use ($termino) {
            return str_contains(strtolower($busqueda), strtolower($termino));
        });
    }
} 