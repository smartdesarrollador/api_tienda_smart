<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDireccionValidadaRequest;
use App\Http\Requests\UpdateDireccionValidadaRequest;
use App\Http\Requests\ValidarDireccionRequest;
use App\Http\Resources\DireccionValidadaResource;
use App\Http\Resources\DireccionResource;
use App\Http\Resources\ZonaRepartoResource;
use App\Models\DireccionValidada;
use App\Models\Direccion;
use App\Models\ZonaReparto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class DireccionValidadaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DireccionValidada::query();

        // Filtros
        if ($request->filled('direccion_id')) {
            $query->where('direccion_id', $request->get('direccion_id'));
        }

        if ($request->filled('zona_reparto_id')) {
            $query->where('zona_reparto_id', $request->get('zona_reparto_id'));
        }

        if ($request->filled('en_zona_cobertura')) {
            $query->where('en_zona_cobertura', $request->boolean('en_zona_cobertura'));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_ultima_validacion', '>=', $request->get('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_ultima_validacion', '<=', $request->get('fecha_hasta'));
        }

        // Relaciones
        $with = [];
        if ($request->filled('with_direccion')) {
            $with[] = 'direccion.distrito.provincia.departamento';
        }
        if ($request->filled('with_zona')) {
            $with[] = 'zonaReparto';
        }

        if (!empty($with)) {
            $query->with($with);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'fecha_ultima_validacion');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Paginación
        $perPage = $request->get('per_page', 15);
        if ($perPage === 'all') {
            return DireccionValidadaResource::collection($query->get());
        }

        $direcciones = $query->paginate($perPage);

        return DireccionValidadaResource::collection($direcciones);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDireccionValidadaRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $data['fecha_ultima_validacion'] = now();

            $direccionValidada = DireccionValidada::create($data);

            DB::commit();

            // Cargar relaciones para la respuesta
            $direccionValidada->load(['direccion', 'zonaReparto']);

            return response()->json([
                'message' => 'Dirección validada creada exitosamente',
                'data' => new DireccionValidadaResource($direccionValidada),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al crear la dirección validada',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, DireccionValidada $direccionValidada): JsonResponse
    {
        // Cargar relaciones según parámetros
        $with = [];
        
        if ($request->filled('with_direccion')) {
            $with[] = 'direccion.distrito.provincia.departamento';
        }
        if ($request->filled('with_zona')) {
            $with[] = 'zonaReparto.horarios';
        }

        if (!empty($with)) {
            $direccionValidada->load($with);
        }

        return response()->json([
            'data' => new DireccionValidadaResource($direccionValidada),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDireccionValidadaRequest $request, DireccionValidada $direccionValidada): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            
            // Actualizar fecha de validación si se cambian datos importantes
            if (isset($data['latitud']) || isset($data['longitud']) || isset($data['zona_reparto_id'])) {
                $data['fecha_ultima_validacion'] = now();
            }

            $direccionValidada->update($data);

            DB::commit();

            // Cargar relaciones para la respuesta
            $direccionValidada->load(['direccion', 'zonaReparto']);

            return response()->json([
                'message' => 'Dirección validada actualizada exitosamente',
                'data' => new DireccionValidadaResource($direccionValidada),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al actualizar la dirección validada',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DireccionValidada $direccionValidada): JsonResponse
    {
        try {
            // Verificar que no tenga pedidos asociados
            if ($direccionValidada->pedidos()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar la dirección validada porque tiene pedidos asociados',
                ], 422);
            }

            $direccionValidada->delete();

            return response()->json([
                'message' => 'Dirección validada eliminada exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la dirección validada',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validar una dirección específica
     */
    public function validarDireccion(ValidarDireccionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            
            $direccion = Direccion::findOrFail($data['direccion_id']);
            
            // Usar coordenadas de la dirección o las proporcionadas
            $latitud = $data['latitud'] ?? $direccion->latitud;
            $longitud = $data['longitud'] ?? $direccion->longitud;

            if (!$latitud || !$longitud) {
                return response()->json([
                    'message' => 'Se requieren coordenadas para validar la dirección',
                ], 422);
            }

            // Encontrar la mejor zona de reparto para esta dirección
            $zonaReparto = $this->encontrarMejorZonaReparto($latitud, $longitud);
            
            if (!$zonaReparto) {
                // Crear registro sin zona (fuera de cobertura)
                $direccionValidada = DireccionValidada::updateOrCreate(
                    ['direccion_id' => $direccion->id],
                    [
                        'latitud' => $latitud,
                        'longitud' => $longitud,
                        'zona_reparto_id' => null,
                        'en_zona_cobertura' => false,
                        'costo_envio_calculado' => null,
                        'distancia_tienda_km' => null,
                        'tiempo_entrega_estimado' => null,
                        'fecha_ultima_validacion' => now(),
                        'observaciones_validacion' => 'Dirección fuera de zona de cobertura',
                    ]
                );

                DB::commit();

                return response()->json([
                    'message' => 'Dirección validada pero está fuera de zona de cobertura',
                    'en_cobertura' => false,
                    'data' => new DireccionValidadaResource($direccionValidada),
                ]);
            }

            // Calcular métricas de entrega
            $distancia = $this->calcularDistancia($zonaReparto, $latitud, $longitud);
            $costoEnvio = $this->calcularCostoEnvio($zonaReparto, $distancia);
            $tiempoEntrega = $this->calcularTiempoEntrega($zonaReparto, $distancia);

            // Crear o actualizar la dirección validada
            $direccionValidada = DireccionValidada::updateOrCreate(
                ['direccion_id' => $direccion->id],
                [
                    'latitud' => $latitud,
                    'longitud' => $longitud,
                    'zona_reparto_id' => $zonaReparto->id,
                    'en_zona_cobertura' => true,
                    'costo_envio_calculado' => $costoEnvio,
                    'distancia_tienda_km' => $distancia,
                    'tiempo_entrega_estimado' => $tiempoEntrega,
                    'fecha_ultima_validacion' => now(),
                    'observaciones_validacion' => $data['observaciones_validacion'] ?? 'Dirección validada automáticamente',
                ]
            );

            // Actualizar coordenadas en la dirección original si no las tenía
            if (!$direccion->latitud || !$direccion->longitud) {
                $direccion->update([
                    'latitud' => $latitud,
                    'longitud' => $longitud,
                    'validada' => true,
                ]);
            }

            DB::commit();

            // Cargar relaciones para la respuesta
            $direccionValidada->load(['direccion', 'zonaReparto']);

            return response()->json([
                'message' => 'Dirección validada exitosamente',
                'en_cobertura' => true,
                'data' => new DireccionValidadaResource($direccionValidada),
                'metricas' => [
                    'distancia_km' => $distancia,
                    'costo_envio' => $costoEnvio,
                    'tiempo_entrega_min' => $tiempoEntrega,
                    'zona_asignada' => $zonaReparto->nombre,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al validar la dirección',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revalidar direcciones existentes
     */
    public function revalidarDirecciones(Request $request): JsonResponse
    {
        $request->validate([
            'direcciones_ids' => 'sometimes|array',
            'direcciones_ids.*' => 'exists:direcciones_validadas,id',
            'zona_reparto_id' => 'sometimes|exists:zonas_reparto,id',
        ]);

        try {
            $query = DireccionValidada::query();

            if ($request->has('direcciones_ids')) {
                $query->whereIn('id', $request->get('direcciones_ids'));
            }

            if ($request->has('zona_reparto_id')) {
                $query->where('zona_reparto_id', $request->get('zona_reparto_id'));
            }

            $direcciones = $query->get();
            $revalidadas = 0;
            $errores = [];

            /** @var DireccionValidada $direccionValidada */
            foreach ($direcciones as $direccionValidada) {
                try {
                    $this->revalidarDireccionIndividual($direccionValidada);
                    $revalidadas++;
                } catch (\Exception $e) {
                    $errores[] = [
                        'direccion_id' => $direccionValidada->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'message' => "Se revalidaron {$revalidadas} direcciones",
                'total_procesadas' => $direcciones->count(),
                'exitosas' => $revalidadas,
                'errores' => $errores,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al revalidar direcciones',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de validación
     */
    public function getEstadisticas(): JsonResponse
    {
        try {
            $estadisticas = [
                'total_direcciones_validadas' => DireccionValidada::count(),
                'en_cobertura' => DireccionValidada::where('en_zona_cobertura', true)->count(),
                'fuera_cobertura' => DireccionValidada::where('en_zona_cobertura', false)->count(),
                'validadas_hoy' => DireccionValidada::whereDate('fecha_ultima_validacion', today())->count(),
                'validadas_semana' => DireccionValidada::whereBetween('fecha_ultima_validacion', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'costo_promedio_envio' => DireccionValidada::where('en_zona_cobertura', true)
                    ->whereNotNull('costo_envio_calculado')
                    ->avg('costo_envio_calculado'),
                'distancia_promedio' => DireccionValidada::where('en_zona_cobertura', true)
                    ->whereNotNull('distancia_tienda_km')
                    ->avg('distancia_tienda_km'),
                'tiempo_promedio_entrega' => DireccionValidada::where('en_zona_cobertura', true)
                    ->whereNotNull('tiempo_entrega_estimado')
                    ->avg('tiempo_entrega_estimado'),
            ];

            // Estadísticas por zona
            $estadisticasPorZona = DireccionValidada::select('zona_reparto_id', DB::raw('count(*) as total'))
                ->with('zonaReparto:id,nombre')
                ->where('en_zona_cobertura', true)
                ->groupBy('zona_reparto_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'zona_id' => $item->zona_reparto_id,
                        'zona_nombre' => $item->zonaReparto->nombre ?? 'Sin zona',
                        'total_direcciones' => $item->total,
                    ];
                });

            return response()->json([
                'estadisticas_generales' => $estadisticas,
                'estadisticas_por_zona' => $estadisticasPorZona,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Métodos privados de apoyo
     */
    private function encontrarMejorZonaReparto(float $latitud, float $longitud): ?ZonaReparto
    {
        $zonasActivas = ZonaReparto::where('activo', true)
            ->with('costosEnvioDinamicos')
            ->get();

        foreach ($zonasActivas as $zona) {
            if ($this->direccionEnZona($zona, $latitud, $longitud)) {
                return $zona;
            }
        }

        return null;
    }

    private function direccionEnZona(ZonaReparto $zona, float $lat, float $lng): bool
    {
        // Verificar por radio de cobertura
        if ($zona->radio_cobertura_km && $zona->coordenadas_centro) {
            $distancia = $this->calcularDistancia($zona, $lat, $lng);
            return $distancia <= $zona->radio_cobertura_km;
        }

        // Verificar por polígono (implementación simplificada)
        if ($zona->poligono_cobertura) {
            // Aquí iría la lógica real de verificación de polígono
            return true; // Placeholder
        }

        return true; // Si no tiene restricciones específicas
    }

    private function calcularDistancia(ZonaReparto $zona, float $lat, float $lng): float
    {
        if (!$zona->coordenadas_centro) {
            return 0;
        }

        $coords = explode(',', $zona->coordenadas_centro);
        $latCentro = (float) $coords[0];
        $lngCentro = (float) $coords[1];

        $earthRadius = 6371; // Radio de la Tierra en km

        $dLat = deg2rad($lat - $latCentro);
        $dLng = deg2rad($lng - $lngCentro);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($latCentro)) * cos(deg2rad($lat)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function calcularCostoEnvio(ZonaReparto $zona, float $distancia): float
    {
        $costoBase = $zona->costo_envio;
        $costoAdicional = 0;

        // Aplicar costos dinámicos por distancia
        foreach ($zona->costosEnvioDinamicos as $costoDinamico) {
            if ($distancia >= $costoDinamico->distancia_km && $costoDinamico->activo) {
                $costoAdicional = max($costoAdicional, $costoDinamico->costo_adicional);
            }
        }

        return $costoBase + $costoAdicional;
    }

    private function calcularTiempoEntrega(ZonaReparto $zona, float $distancia): int
    {
        $tiempoBase = $zona->tiempo_entrega_min ?? 30;
        
        // Agregar tiempo por distancia (ejemplo: 5 minutos por km adicional)
        $tiempoAdicional = ($distancia > 1) ? (int) (($distancia - 1) * 5) : 0;

        return $tiempoBase + $tiempoAdicional;
    }

    private function revalidarDireccionIndividual(DireccionValidada $direccionValidada): void
    {
        if (!$direccionValidada->latitud || !$direccionValidada->longitud) {
            throw new \Exception('Dirección sin coordenadas válidas');
        }

        $zonaReparto = $this->encontrarMejorZonaReparto(
            $direccionValidada->latitud,
            $direccionValidada->longitud
        );

        if (!$zonaReparto) {
            $direccionValidada->update([
                'zona_reparto_id' => null,
                'en_zona_cobertura' => false,
                'costo_envio_calculado' => null,
                'fecha_ultima_validacion' => now(),
                'observaciones_validacion' => 'Revalidación: Fuera de cobertura',
            ]);
            return;
        }

        $distancia = $this->calcularDistancia($zonaReparto, $direccionValidada->latitud, $direccionValidada->longitud);
        $costoEnvio = $this->calcularCostoEnvio($zonaReparto, $distancia);
        $tiempoEntrega = $this->calcularTiempoEntrega($zonaReparto, $distancia);

        $direccionValidada->update([
            'zona_reparto_id' => $zonaReparto->id,
            'en_zona_cobertura' => true,
            'costo_envio_calculado' => $costoEnvio,
            'distancia_tienda_km' => $distancia,
            'tiempo_entrega_estimado' => $tiempoEntrega,
            'fecha_ultima_validacion' => now(),
            'observaciones_validacion' => 'Revalidación automática',
        ]);
    }
} 