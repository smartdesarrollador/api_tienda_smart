<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SeguimientoPedidoResource;
use App\Models\SeguimientoPedido;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SeguimientoPedidoController extends Controller
{
    private const ESTADOS_PEDIDO = [
        'pendiente',
        'confirmado',
        'preparando',
        'listo',
        'enviado',
        'entregado',
        'cancelado',
        'devuelto'
    ];

    /**
     * Obtener listado de seguimientos de pedido
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $query = SeguimientoPedido::query()
                ->with(['pedido', 'usuarioCambio']);

            // Filtros
            if ($request->has('pedido_id')) {
                $query->where('pedido_id', $request->pedido_id);
            }

            if ($request->has('estado_actual')) {
                $query->where('estado_actual', $request->estado_actual);
            }

            if ($request->has('usuario_cambio_id')) {
                $query->where('usuario_cambio_id', $request->usuario_cambio_id);
            }

            if ($request->has('fecha_desde')) {
                $query->where('fecha_cambio', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->where('fecha_cambio', '<=', $request->fecha_hasta);
            }

            if ($request->boolean('notificado_cliente')) {
                $query->where('notificado_cliente', true);
            }

            if ($request->boolean('sin_notificar')) {
                $query->where('notificado_cliente', false);
            }

            // Ordenamiento por fecha de cambio
            $query->orderBy('fecha_cambio', 'desc');

            $seguimientos = $query->paginate($request->input('per_page', 15));

            return SeguimientoPedidoResource::collection($seguimientos);
        } catch (\Exception $e) {
            Log::error('Error al obtener seguimientos de pedido: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crear nuevo seguimiento de pedido
     */
    public function store(Request $request): SeguimientoPedidoResource
    {
        try {
            $validated = $request->validate([
                'pedido_id' => 'required|exists:pedidos,id',
                'estado_anterior' => 'required|in:' . implode(',', self::ESTADOS_PEDIDO),
                'estado_actual' => 'required|in:' . implode(',', self::ESTADOS_PEDIDO),
                'observaciones' => 'nullable|string|max:500',
                'usuario_cambio_id' => 'required|exists:users,id',
                'latitud_seguimiento' => 'nullable|numeric|between:-90,90',
                'longitud_seguimiento' => 'nullable|numeric|between:-180,180',
                'tiempo_estimado_restante' => 'nullable|integer|min:0',
                'fecha_cambio' => 'nullable|date',
                'notificado_cliente' => 'boolean',
            ]);

            // Verificar que el estado anterior coincida con el último estado del pedido
            $pedido = Pedido::findOrFail($validated['pedido_id']);
            if ($pedido->estado !== $validated['estado_anterior']) {
                throw ValidationException::withMessages([
                    'estado_anterior' => ['El estado anterior no coincide con el estado actual del pedido.']
                ]);
            }

            DB::beginTransaction();
            
            // Crear el seguimiento
            $seguimiento = SeguimientoPedido::create($validated);
            
            // Actualizar el estado del pedido
            $pedido->update(['estado' => $validated['estado_actual']]);
            
            // Cargar relaciones necesarias
            $seguimiento->load(['pedido', 'usuarioCambio']);
            
            DB::commit();

            return new SeguimientoPedidoResource($seguimiento);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear seguimiento de pedido: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener un seguimiento de pedido específico
     */
    public function show(int $id): SeguimientoPedidoResource
    {
        try {
            $seguimiento = SeguimientoPedido::with(['pedido', 'usuarioCambio'])
                ->findOrFail($id);

            return new SeguimientoPedidoResource($seguimiento);
        } catch (\Exception $e) {
            Log::error('Error al obtener seguimiento de pedido: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar un seguimiento de pedido
     */
    public function update(Request $request, int $id): SeguimientoPedidoResource
    {
        try {
            $seguimiento = SeguimientoPedido::findOrFail($id);

            $validated = $request->validate([
                'observaciones' => 'nullable|string|max:500',
                'latitud_seguimiento' => 'nullable|numeric|between:-90,90',
                'longitud_seguimiento' => 'nullable|numeric|between:-180,180',
                'tiempo_estimado_restante' => 'nullable|integer|min:0',
                'notificado_cliente' => 'boolean',
            ]);

            DB::beginTransaction();
            
            $seguimiento->update($validated);
            
            // Cargar relaciones necesarias
            $seguimiento->load(['pedido', 'usuarioCambio']);
            
            DB::commit();

            return new SeguimientoPedidoResource($seguimiento);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar seguimiento de pedido: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar un seguimiento de pedido
     */
    public function destroy(int $id): Response
    {
        try {
            $seguimiento = SeguimientoPedido::findOrFail($id);

            // Verificar si es el último seguimiento del pedido
            $esUltimo = SeguimientoPedido::where('pedido_id', $seguimiento->pedido_id)
                ->orderBy('fecha_cambio', 'desc')
                ->first()->id === $id;

            if ($esUltimo) {
                throw ValidationException::withMessages([
                    'id' => ['No se puede eliminar el último seguimiento de un pedido.']
                ]);
            }

            DB::beginTransaction();
            
            $seguimiento->delete();
            
            DB::commit();

            return response()->noContent();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar seguimiento de pedido: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Marcar seguimiento como notificado al cliente
     */
    public function marcarNotificado(int $id): SeguimientoPedidoResource
    {
        try {
            $seguimiento = SeguimientoPedido::findOrFail($id);

            DB::beginTransaction();
            
            $seguimiento->update(['notificado_cliente' => true]);
            
            // Cargar relaciones necesarias
            $seguimiento->load(['pedido', 'usuarioCambio']);
            
            DB::commit();

            return new SeguimientoPedidoResource($seguimiento);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al marcar seguimiento como notificado: ' . $e->getMessage());
            throw $e;
        }
    }
} 