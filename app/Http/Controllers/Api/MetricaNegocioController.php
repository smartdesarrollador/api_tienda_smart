<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\MetricasNegocioResource;
use App\Models\MetricaNegocio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MetricaNegocioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $metricas = MetricaNegocio::query()
            ->when($request->fecha_desde, fn($q) => $q->where('fecha', '>=', $request->fecha_desde))
            ->when($request->fecha_hasta, fn($q) => $q->where('fecha', '<=', $request->fecha_hasta))
            ->when($request->mes, function($q) use ($request) {
                $year = $request->year ?? now()->year;
                return $q->whereYear('fecha', $year)->whereMonth('fecha', $request->mes);
            })
            ->when($request->year, fn($q) => $q->whereYear('fecha', $request->year))
            ->orderBy('fecha', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => MetricasNegocioResource::collection($metricas),
            'meta' => [
                'total' => $metricas->total(),
                'per_page' => $metricas->perPage(),
                'current_page' => $metricas->currentPage(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date|unique:metricas_negocio,fecha',
            'pedidos_totales' => 'required|integer|min:0',
            'pedidos_entregados' => 'required|integer|min:0',
            'pedidos_cancelados' => 'required|integer|min:0',
            'ventas_totales' => 'required|numeric|min:0',
            'costo_envios' => 'required|numeric|min:0',
            'nuevos_clientes' => 'required|integer|min:0',
            'clientes_recurrentes' => 'required|integer|min:0',
            'tiempo_promedio_entrega' => 'required|numeric|min:0',
            'productos_vendidos' => 'required|integer|min:0',
            'ticket_promedio' => 'required|numeric|min:0',
            'productos_mas_vendidos' => 'nullable|array',
            'zonas_mas_activas' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $metrica = MetricaNegocio::create($request->all());

            DB::commit();

            return response()->json([
                'message' => 'Métrica de negocio creada exitosamente',
                'data' => new MetricasNegocioResource($metrica)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la métrica de negocio'], 500);
        }
    }

    public function show(MetricaNegocio $metricaNegocio): JsonResponse
    {
        return response()->json([
            'data' => new MetricasNegocioResource($metricaNegocio)
        ]);
    }

    public function update(Request $request, MetricaNegocio $metricaNegocio): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'sometimes|required|date|unique:metricas_negocio,fecha,' . $metricaNegocio->id,
            'pedidos_totales' => 'sometimes|required|integer|min:0',
            'pedidos_entregados' => 'sometimes|required|integer|min:0',
            'pedidos_cancelados' => 'sometimes|required|integer|min:0',
            'ventas_totales' => 'sometimes|required|numeric|min:0',
            'costo_envios' => 'sometimes|required|numeric|min:0',
            'nuevos_clientes' => 'sometimes|required|integer|min:0',
            'clientes_recurrentes' => 'sometimes|required|integer|min:0',
            'tiempo_promedio_entrega' => 'sometimes|required|numeric|min:0',
            'productos_vendidos' => 'sometimes|required|integer|min:0',
            'ticket_promedio' => 'sometimes|required|numeric|min:0',
            'productos_mas_vendidos' => 'nullable|array',
            'zonas_mas_activas' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $metricaNegocio->update($request->all());

            DB::commit();

            return response()->json([
                'message' => 'Métrica de negocio actualizada exitosamente',
                'data' => new MetricasNegocioResource($metricaNegocio)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar la métrica de negocio'], 500);
        }
    }

    public function destroy(MetricaNegocio $metricaNegocio): JsonResponse
    {
        try {
            $metricaNegocio->delete();
            return response()->json(['message' => 'Métrica de negocio eliminada exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la métrica de negocio'], 500);
        }
    }

    public function resumenPeriodo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $metricas = MetricaNegocio::whereBetween('fecha', [$request->fecha_desde, $request->fecha_hasta])->get();

            $resumen = [
                'periodo' => [
                    'desde' => $request->fecha_desde,
                    'hasta' => $request->fecha_hasta,
                    'dias' => $metricas->count(),
                ],
                'totales' => [
                    'pedidos_totales' => $metricas->sum('pedidos_totales'),
                    'pedidos_entregados' => $metricas->sum('pedidos_entregados'),
                    'pedidos_cancelados' => $metricas->sum('pedidos_cancelados'),
                    'ventas_totales' => $metricas->sum('ventas_totales'),
                    'costo_envios' => $metricas->sum('costo_envios'),
                    'nuevos_clientes' => $metricas->sum('nuevos_clientes'),
                    'clientes_recurrentes' => $metricas->sum('clientes_recurrentes'),
                    'productos_vendidos' => $metricas->sum('productos_vendidos'),
                ],
                'promedios' => [
                    'pedidos_por_dia' => $metricas->avg('pedidos_totales'),
                    'ventas_por_dia' => $metricas->avg('ventas_totales'),
                    'ticket_promedio' => $metricas->avg('ticket_promedio'),
                    'tiempo_promedio_entrega' => $metricas->avg('tiempo_promedio_entrega'),
                ],
                'kpis' => [
                    'tasa_entrega_promedio' => $this->calcularTasaEntregaPromedio($metricas),
                    'tasa_cancelacion_promedio' => $this->calcularTasaCancelacionPromedio($metricas),
                    'rentabilidad_total' => $metricas->sum('ventas_totales') - $metricas->sum('costo_envios'),
                    'crecimiento_ventas' => $this->calcularCrecimientoVentas($metricas),
                ],
            ];

            return response()->json([
                'resumen' => $resumen,
                'metricas_detalle' => MetricasNegocioResource::collection($metricas)
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al generar el resumen del período'], 500);
        }
    }

    public function generarMetricasDiarias(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $fecha = Carbon::parse($request->fecha);
            
            // Verificar si ya existe métrica para esta fecha
            $metricaExistente = MetricaNegocio::where('fecha', $fecha->format('Y-m-d'))->first();
            if ($metricaExistente) {
                return response()->json(['message' => 'Ya existe una métrica para esta fecha'], 400);
            }

            $metricas = $this->calcularMetricasDia($fecha);

            $metrica = MetricaNegocio::create($metricas);

            return response()->json([
                'message' => 'Métricas diarias generadas exitosamente',
                'data' => new MetricasNegocioResource($metrica)
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al generar las métricas diarias'], 500);
        }
    }

    private function calcularTasaEntregaPromedio($metricas): float
    {
        $totalPedidos = $metricas->sum('pedidos_totales');
        $totalEntregados = $metricas->sum('pedidos_entregados');
        
        return $totalPedidos > 0 ? round(($totalEntregados / $totalPedidos) * 100, 2) : 0.0;
    }

    private function calcularTasaCancelacionPromedio($metricas): float
    {
        $totalPedidos = $metricas->sum('pedidos_totales');
        $totalCancelados = $metricas->sum('pedidos_cancelados');
        
        return $totalPedidos > 0 ? round(($totalCancelados / $totalPedidos) * 100, 2) : 0.0;
    }

    private function calcularCrecimientoVentas($metricas): float
    {
        if ($metricas->count() < 2) {
            return 0.0;
        }

        $metricasOrdenadas = $metricas->sortBy('fecha');
        $primera = $metricasOrdenadas->first();
        $ultima = $metricasOrdenadas->last();

        if ($primera->ventas_totales == 0) {
            return 0.0;
        }

        return round((($ultima->ventas_totales - $primera->ventas_totales) / $primera->ventas_totales) * 100, 2);
    }

    private function calcularMetricasDia(Carbon $fecha): array
    {
        // Aquí implementarías la lógica para calcular las métricas del día
        // basándote en los datos reales de pedidos, ventas, etc.
        
        return [
            'fecha' => $fecha->format('Y-m-d'),
            'pedidos_totales' => 0,
            'pedidos_entregados' => 0,
            'pedidos_cancelados' => 0,
            'ventas_totales' => 0.0,
            'costo_envios' => 0.0,
            'nuevos_clientes' => 0,
            'clientes_recurrentes' => 0,
            'tiempo_promedio_entrega' => 0.0,
            'productos_vendidos' => 0,
            'ticket_promedio' => 0.0,
            'productos_mas_vendidos' => [],
            'zonas_mas_activas' => [],
        ];
    }
} 