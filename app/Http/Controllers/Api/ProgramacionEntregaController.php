<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\ProgramacionEntregaResource;
use App\Models\ProgramacionEntrega;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ProgramacionEntregaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $programaciones = ProgramacionEntrega::query()
            ->with(['pedido', 'repartidor'])
            ->when($request->pedido_id, fn($q) => $q->where('pedido_id', $request->pedido_id))
            ->when($request->repartidor_id, fn($q) => $q->where('repartidor_id', $request->repartidor_id))
            ->when($request->estado, fn($q) => $q->where('estado', $request->estado))
            ->when($request->fecha_programada, fn($q) => $q->whereDate('fecha_programada', $request->fecha_programada))
            ->when($request->fecha_desde, fn($q) => $q->where('fecha_programada', '>=', $request->fecha_desde))
            ->when($request->fecha_hasta, fn($q) => $q->where('fecha_programada', '<=', $request->fecha_hasta))
            ->when($request->hoy, fn($q) => $q->whereDate('fecha_programada', today()))
            ->orderBy('fecha_programada', 'asc')
            ->orderBy('orden_ruta', 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => ProgramacionEntregaResource::collection($programaciones),
            'meta' => [
                'total' => $programaciones->total(),
                'per_page' => $programaciones->perPage(),
                'current_page' => $programaciones->currentPage(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pedido_id' => 'required|exists:pedidos,id',
            'repartidor_id' => 'required|exists:users,id',
            'fecha_programada' => 'required|date|after_or_equal:today',
            'hora_inicio_ventana' => 'required|date_format:H:i',
            'hora_fin_ventana' => 'required|date_format:H:i|after:hora_inicio_ventana',
            'orden_ruta' => 'nullable|integer|min:1',
            'notas_repartidor' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Verificar que no exista ya una programación para este pedido
            $programacionExistente = ProgramacionEntrega::where('pedido_id', $request->pedido_id)
                ->whereIn('estado', ['programado', 'en_ruta'])
                ->first();

            if ($programacionExistente) {
                return response()->json(['message' => 'Ya existe una programación activa para este pedido'], 400);
            }

            // Asignar orden de ruta automáticamente si no se proporciona
            $ordenRuta = $request->orden_ruta;
            if (!$ordenRuta) {
                $ordenRuta = $this->obtenerSiguienteOrdenRuta($request->repartidor_id, $request->fecha_programada);
            }

            $programacion = ProgramacionEntrega::create([
                'pedido_id' => $request->pedido_id,
                'repartidor_id' => $request->repartidor_id,
                'fecha_programada' => $request->fecha_programada,
                'hora_inicio_ventana' => $request->hora_inicio_ventana,
                'hora_fin_ventana' => $request->hora_fin_ventana,
                'estado' => 'programado',
                'orden_ruta' => $ordenRuta,
                'notas_repartidor' => $request->notas_repartidor,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Programación de entrega creada exitosamente',
                'data' => new ProgramacionEntregaResource($programacion->load(['pedido', 'repartidor']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la programación de entrega'], 500);
        }
    }

    public function show(ProgramacionEntrega $programacionEntrega): JsonResponse
    {
        return response()->json([
            'data' => new ProgramacionEntregaResource($programacionEntrega->load(['pedido', 'repartidor']))
        ]);
    }

    public function update(Request $request, ProgramacionEntrega $programacionEntrega): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repartidor_id' => 'sometimes|required|exists:users,id',
            'fecha_programada' => 'sometimes|required|date|after_or_equal:today',
            'hora_inicio_ventana' => 'sometimes|required|date_format:H:i',
            'hora_fin_ventana' => 'sometimes|required|date_format:H:i|after:hora_inicio_ventana',
            'orden_ruta' => 'nullable|integer|min:1',
            'notas_repartidor' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Verificar que la programación se pueda modificar
            if (in_array($programacionEntrega->estado, ['entregado', 'fallido'])) {
                return response()->json(['message' => 'No se puede modificar una programación finalizada'], 400);
            }

            $programacionEntrega->update($request->only([
                'repartidor_id',
                'fecha_programada',
                'hora_inicio_ventana',
                'hora_fin_ventana',
                'orden_ruta',
                'notas_repartidor'
            ]));

            DB::commit();

            return response()->json([
                'message' => 'Programación de entrega actualizada exitosamente',
                'data' => new ProgramacionEntregaResource($programacionEntrega->load(['pedido', 'repartidor']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar la programación de entrega'], 500);
        }
    }

    public function destroy(ProgramacionEntrega $programacionEntrega): JsonResponse
    {
        try {
            if (in_array($programacionEntrega->estado, ['en_ruta', 'entregado'])) {
                return response()->json(['message' => 'No se puede eliminar una programación en curso o finalizada'], 400);
            }

            $programacionEntrega->delete();
            return response()->json(['message' => 'Programación de entrega eliminada exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la programación de entrega'], 500);
        }
    }

    public function cambiarEstado(Request $request, ProgramacionEntrega $programacionEntrega): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:programado,en_ruta,entregado,fallido,reprogramado',
            'motivo_fallo' => 'required_if:estado,fallido|string|max:255',
            'notas_repartidor' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $estadoAnterior = $programacionEntrega->estado;
            $nuevoEstado = $request->estado;

            // Validar transiciones de estado
            if (!$this->esTransicionValida($estadoAnterior, $nuevoEstado)) {
                return response()->json(['message' => 'Transición de estado no válida'], 400);
            }

            // Actualizar campos según el nuevo estado
            $datosActualizacion = ['estado' => $nuevoEstado];

            if ($request->has('notas_repartidor')) {
                $datosActualizacion['notas_repartidor'] = $request->notas_repartidor;
            }

            switch ($nuevoEstado) {
                case 'en_ruta':
                    $datosActualizacion['hora_salida'] = now();
                    break;
                case 'entregado':
                    $datosActualizacion['hora_llegada'] = now();
                    break;
                case 'fallido':
                    $datosActualizacion['motivo_fallo'] = $request->motivo_fallo;
                    break;
            }

            $programacionEntrega->update($datosActualizacion);

            DB::commit();

            return response()->json([
                'message' => "Estado cambiado a {$nuevoEstado} exitosamente",
                'data' => new ProgramacionEntregaResource($programacionEntrega->load(['pedido', 'repartidor']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al cambiar el estado de la programación'], 500);
        }
    }

    public function rutaRepartidor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repartidor_id' => 'required|exists:users,id',
            'fecha' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $programaciones = ProgramacionEntrega::with(['pedido', 'repartidor'])
                ->where('repartidor_id', $request->repartidor_id)
                ->whereDate('fecha_programada', $request->fecha)
                ->orderBy('orden_ruta', 'asc')
                ->get();

            $resumen = [
                'repartidor_id' => $request->repartidor_id,
                'fecha' => $request->fecha,
                'total_entregas' => $programaciones->count(),
                'entregas_completadas' => $programaciones->where('estado', 'entregado')->count(),
                'entregas_pendientes' => $programaciones->whereIn('estado', ['programado', 'en_ruta'])->count(),
                'entregas_fallidas' => $programaciones->where('estado', 'fallido')->count(),
            ];

            return response()->json([
                'resumen' => $resumen,
                'ruta' => ProgramacionEntregaResource::collection($programaciones)
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener la ruta del repartidor'], 500);
        }
    }

    public function reprogramar(Request $request, ProgramacionEntrega $programacionEntrega): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nueva_fecha' => 'required|date|after_or_equal:today',
            'nueva_hora_inicio' => 'required|date_format:H:i',
            'nueva_hora_fin' => 'required|date_format:H:i|after:nueva_hora_inicio',
            'motivo_reprogramacion' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $programacionEntrega->update([
                'fecha_programada' => $request->nueva_fecha,
                'hora_inicio_ventana' => $request->nueva_hora_inicio,
                'hora_fin_ventana' => $request->nueva_hora_fin,
                'estado' => 'reprogramado',
                'motivo_fallo' => $request->motivo_reprogramacion,
                'hora_salida' => null,
                'hora_llegada' => null,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Entrega reprogramada exitosamente',
                'data' => new ProgramacionEntregaResource($programacionEntrega->load(['pedido', 'repartidor']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al reprogramar la entrega'], 500);
        }
    }

    private function obtenerSiguienteOrdenRuta(int $repartidorId, string $fecha): int
    {
        $ultimoOrden = ProgramacionEntrega::where('repartidor_id', $repartidorId)
            ->whereDate('fecha_programada', $fecha)
            ->max('orden_ruta');

        return ($ultimoOrden ?? 0) + 1;
    }

    private function esTransicionValida(string $estadoActual, string $nuevoEstado): bool
    {
        $transicionesValidas = [
            'programado' => ['en_ruta', 'fallido', 'reprogramado'],
            'en_ruta' => ['entregado', 'fallido'],
            'entregado' => [], // Estado final
            'fallido' => ['reprogramado'],
            'reprogramado' => ['programado', 'en_ruta'],
        ];

        return in_array($nuevoEstado, $transicionesValidas[$estadoActual] ?? []);
    }
} 