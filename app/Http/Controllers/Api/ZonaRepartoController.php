<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreZonaRepartoRequest;
use App\Http\Requests\UpdateZonaRepartoRequest;
use App\Http\Resources\ZonaRepartoResource;
use App\Http\Resources\DistritoResource;
use App\Http\Resources\HorarioZonaResource;
use App\Models\ZonaReparto;
use App\Models\Distrito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ZonaRepartoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ZonaReparto::query();

        // Filtros
        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('disponible_24h')) {
            $query->where('disponible_24h', $request->boolean('disponible_24h'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'LIKE', "%{$search}%")
                  ->orWhere('descripcion', 'LIKE', "%{$search}%")
                  ->orWhere('slug', 'LIKE', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'orden');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Relaciones
        $with = ['distritos'];
        if ($request->filled('with_horarios')) {
            $with[] = 'horarios';
        }
        if ($request->filled('with_costos')) {
            $with[] = 'costosEnvioDinamicos';
        }
        if ($request->filled('with_excepciones')) {
            $with[] = 'excepciones';
        }

        $query->with($with);

        // Paginación
        $perPage = $request->get('per_page', 15);
        if ($perPage === 'all') {
            return ZonaRepartoResource::collection($query->get());
        }

        $zonas = $query->paginate($perPage);

        return ZonaRepartoResource::collection($zonas);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreZonaRepartoRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            
            // Generar slug si no se proporciona
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['nombre']);
            }

            // Asegurar que el slug es único
            $data['slug'] = $this->generateUniqueSlug($data['slug']);

            $zonaReparto = ZonaReparto::create($data);

            // Asociar distritos si se proporcionan
            if ($request->has('distritos_ids')) {
                $zonaReparto->distritos()->sync($request->get('distritos_ids'));
            }

            DB::commit();

            // Cargar relaciones para la respuesta
            $zonaReparto->load(['distritos', 'horarios']);

            return response()->json([
                'message' => 'Zona de reparto creada exitosamente',
                'data' => new ZonaRepartoResource($zonaReparto),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al crear la zona de reparto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, ZonaReparto $zonaReparto): JsonResponse
    {
        // Cargar relaciones según parámetros
        $with = ['distritos'];
        
        if ($request->filled('with_horarios')) {
            $with[] = 'horarios';
        }
        if ($request->filled('with_costos')) {
            $with[] = 'costosEnvioDinamicos';
        }
        if ($request->filled('with_excepciones')) {
            $with[] = 'excepciones';
        }
        if ($request->filled('with_zona_distritos')) {
            $with[] = 'zonaDistritos';
        }

        $zonaReparto->load($with);

        return response()->json([
            'data' => new ZonaRepartoResource($zonaReparto),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateZonaRepartoRequest $request, ZonaReparto $zonaReparto): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Actualizar slug si se cambió el nombre
            if (isset($data['nombre']) && $data['nombre'] !== $zonaReparto->nombre) {
                if (empty($data['slug'])) {
                    $data['slug'] = Str::slug($data['nombre']);
                }
                $data['slug'] = $this->generateUniqueSlug($data['slug'], $zonaReparto->id);
            }

            $zonaReparto->update($data);

            // Actualizar distritos si se proporcionan
            if ($request->has('distritos_ids')) {
                $zonaReparto->distritos()->sync($request->get('distritos_ids'));
            }

            DB::commit();

            // Cargar relaciones para la respuesta
            $zonaReparto->load(['distritos', 'horarios']);

            return response()->json([
                'message' => 'Zona de reparto actualizada exitosamente',
                'data' => new ZonaRepartoResource($zonaReparto),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al actualizar la zona de reparto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ZonaReparto $zonaReparto): JsonResponse
    {
        try {
            // Verificar que no tenga pedidos asociados
            if ($zonaReparto->pedidos()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar la zona de reparto porque tiene pedidos asociados',
                ], 422);
            }

            $zonaReparto->delete();

            return response()->json([
                'message' => 'Zona de reparto eliminada exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la zona de reparto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activar/Desactivar zona de reparto
     */
    public function toggleStatus(ZonaReparto $zonaReparto): JsonResponse
    {
        try {
            $zonaReparto->update([
                'activo' => !$zonaReparto->activo,
            ]);

            $estado = $zonaReparto->activo ? 'activada' : 'desactivada';

            return response()->json([
                'message' => "Zona de reparto {$estado} exitosamente",
                'data' => new ZonaRepartoResource($zonaReparto),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar el estado de la zona de reparto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener distritos disponibles para asignar
     */
    public function getDistritosDisponibles(): AnonymousResourceCollection
    {
        $distritos = Distrito::where('activo', true)
            ->where('disponible_delivery', true)
            ->with(['provincia.departamento'])
            ->orderBy('nombre')
            ->get();

        return DistritoResource::collection($distritos);
    }

    /**
     * Calcular costo de envío para una dirección
     */
    public function calcularCostoEnvio(Request $request, ZonaReparto $zonaReparto): JsonResponse
    {
        $request->validate([
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'monto_pedido' => 'sometimes|numeric|min:0',
        ]);

        try {
            $latitud = $request->get('latitud');
            $longitud = $request->get('longitud');
            $montoPedido = $request->get('monto_pedido', 0);

            // Verificar si está en zona de cobertura
            $enCobertura = $this->verificarCobertura($zonaReparto, $latitud, $longitud);

            if (!$enCobertura) {
                return response()->json([
                    'en_cobertura' => false,
                    'mensaje' => 'La dirección está fuera de la zona de cobertura',
                ]);
            }

            // Calcular distancia desde el centro de la zona
            $distancia = $this->calcularDistancia(
                $zonaReparto->coordenadas_centro,
                "{$latitud},{$longitud}"
            );

            // Calcular costo base
            $costoBase = $zonaReparto->costo_envio;

            // Aplicar costos dinámicos por distancia
            $costoAdicional = $this->calcularCostoAdicionalPorDistancia($zonaReparto, $distancia);
            
            // Verificar pedido mínimo
            $cumplePedidoMinimo = $zonaReparto->pedido_minimo ? $montoPedido >= $zonaReparto->pedido_minimo : true;

            $costoTotal = $costoBase + $costoAdicional;

            // Aplicar envío gratis si cumple pedido mínimo y está configurado
            if ($cumplePedidoMinimo && $zonaReparto->envio_gratis_desde && $montoPedido >= $zonaReparto->envio_gratis_desde) {
                $costoTotal = 0;
            }

            return response()->json([
                'en_cobertura' => true,
                'distancia_km' => round($distancia, 2),
                'costo_base' => $costoBase,
                'costo_adicional' => $costoAdicional,
                'costo_total' => $costoTotal,
                'cumple_pedido_minimo' => $cumplePedidoMinimo,
                'pedido_minimo' => $zonaReparto->pedido_minimo,
                'tiempo_entrega_estimado' => $zonaReparto->tiempo_entrega_promedio ?? null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al calcular el costo de envío',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener horarios de una zona
     */
    public function getHorarios(ZonaReparto $zonaReparto): AnonymousResourceCollection
    {
        $horarios = $zonaReparto->horarios()
            ->where('activo', true)
            ->orderBy('dia_semana')
            ->get();

        return HorarioZonaResource::collection($horarios);
    }

    /**
     * Verificar disponibilidad en horario específico
     */
    public function verificarDisponibilidad(Request $request, ZonaReparto $zonaReparto): JsonResponse
    {
        $request->validate([
            'fecha_hora' => 'required|date_format:Y-m-d H:i:s',
        ]);

        try {
            $fechaHora = $request->get('fecha_hora');
            $disponible = $this->estaDisponibleEnHorario($zonaReparto, $fechaHora);

            return response()->json([
                'disponible' => $disponible,
                'zona' => $zonaReparto->nombre,
                'fecha_consulta' => $fechaHora,
                'es_24h' => $zonaReparto->disponible_24h,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al verificar disponibilidad',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de una zona de reparto
     */
    public function getEstadisticas(ZonaReparto $zonaReparto): JsonResponse
    {
        try {
            // Estadísticas básicas de la zona
            $estadisticas = [
                'zona_info' => [
                    'id' => $zonaReparto->id,
                    'nombre' => $zonaReparto->nombre,
                    'activo' => $zonaReparto->activo,
                    'disponible_24h' => $zonaReparto->disponible_24h,
                ],
                'cobertura' => [
                    'radio_km' => $zonaReparto->radio_cobertura_km,
                    'tiene_poligono' => !empty($zonaReparto->poligono_cobertura),
                    'distritos_asignados' => $zonaReparto->distritos()->count(),
                ],
                'costos' => [
                    'costo_base' => $zonaReparto->costo_envio,
                    'pedido_minimo' => $zonaReparto->pedido_minimo,
                    'envio_gratis_desde' => $zonaReparto->envio_gratis_desde,
                    'costos_dinamicos' => $zonaReparto->costosEnvioDinamicos()->count(),
                ],
                'horarios' => [
                    'total_horarios' => $zonaReparto->horarios()->count(),
                    'horarios_activos' => $zonaReparto->horarios()->where('activo', true)->count(),
                ],
                'actividad' => [
                    'total_pedidos' => $zonaReparto->pedidos()->count(),
                    'pedidos_mes_actual' => $zonaReparto->pedidos()
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->count(),
                    'pedidos_pendientes' => $zonaReparto->pedidos()
                        ->whereIn('estado', ['pendiente', 'procesando'])
                        ->count(),
                ],
            ];

            // Estadísticas de rendimiento si hay pedidos
            if ($estadisticas['actividad']['total_pedidos'] > 0) {
                $estadisticas['rendimiento'] = [
                    'tiempo_promedio_entrega' => $zonaReparto->tiempo_entrega_promedio,
                    'tasa_entrega_exitosa' => $this->calcularTasaEntregaExitosa($zonaReparto),
                    'pedidos_por_dia_promedio' => $this->calcularPedidosPorDiaPromedio($zonaReparto),
                ];
            }

            return response()->json([
                'data' => $estadisticas,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener estadísticas de la zona',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Métodos privados de apoyo
     */
    private function generateUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (ZonaReparto::where('slug', $slug)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists()
        ) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function verificarCobertura(ZonaReparto $zona, float $lat, float $lng): bool
    {
        // Si tiene radio de cobertura, verificar distancia
        if ($zona->radio_cobertura_km && $zona->coordenadas_centro) {
            $distancia = $this->calcularDistancia($zona->coordenadas_centro, "{$lat},{$lng}");
            return $distancia <= $zona->radio_cobertura_km;
        }

        // Si tiene polígono, verificar si está dentro
        if ($zona->poligono_cobertura) {
            return $this->puntoEnPoligono($lat, $lng, $zona->poligono_cobertura);
        }

        // Por defecto, si no tiene restricciones específicas
        return true;
    }

    private function calcularDistancia(string $coordenadas1, string $coordenadas2): float
    {
        $coords1 = explode(',', $coordenadas1);
        $coords2 = explode(',', $coordenadas2);

        $lat1 = (float) $coords1[0];
        $lng1 = (float) $coords1[1];
        $lat2 = (float) $coords2[0];
        $lng2 = (float) $coords2[1];

        $earthRadius = 6371; // Radio de la Tierra en km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function calcularCostoAdicionalPorDistancia(ZonaReparto $zona, float $distancia): float
    {
        $costoAdicional = 0;

        // Buscar en costos dinámicos configurados
        foreach ($zona->costosEnvioDinamicos as $costoDinamico) {
            if ($distancia >= $costoDinamico->distancia_km && $costoDinamico->activo) {
                $costoAdicional = max($costoAdicional, $costoDinamico->costo_adicional);
            }
        }

        return $costoAdicional;
    }

    private function puntoEnPoligono(float $lat, float $lng, string $poligono): bool
    {
        // Implementación básica - en producción usar una librería especializada
        // Este es un ejemplo simplificado
        return true; // Placeholder
    }

    private function estaDisponibleEnHorario(ZonaReparto $zona, string $fechaHora): bool
    {
        if ($zona->disponible_24h) {
            return true;
        }

        $fecha = new \DateTime($fechaHora);
        $diaSemana = (int) $fecha->format('w'); // 0 = Domingo, 6 = Sábado
        $hora = $fecha->format('H:i:s');

        return $zona->horarios()
            ->where('activo', true)
            ->where('dia_semana', $diaSemana)
            ->where('hora_inicio', '<=', $hora)
            ->where('hora_fin', '>=', $hora)
            ->exists();
    }

    private function calcularTasaEntregaExitosa(ZonaReparto $zona): float
    {
        $totalPedidos = $zona->pedidos()->count();
        if ($totalPedidos === 0) {
            return 0.0;
        }

        $pedidosEntregados = $zona->pedidos()
            ->where('estado', 'entregado')
            ->count();

        return round(($pedidosEntregados / $totalPedidos) * 100, 2);
    }

    private function calcularPedidosPorDiaPromedio(ZonaReparto $zona): float
    {
        $fechaInicio = $zona->created_at;
        $fechaFin = now();
        $diasTranscurridos = $fechaInicio->diffInDays($fechaFin);
        
        if ($diasTranscurridos === 0) {
            $diasTranscurridos = 1;
        }

        $totalPedidos = $zona->pedidos()->count();

        return round($totalPedidos / $diasTranscurridos, 2);
    }
} 