<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DetalleAdicionalResource;
use App\Models\DetalleAdicional;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class DetalleAdicionalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DetalleAdicional::query();
        if ($request->filled('detalle_pedido_id')) {
            $query->where('detalle_pedido_id', $request->detalle_pedido_id);
        }
        if ($request->filled('adicional_id')) {
            $query->where('adicional_id', $request->adicional_id);
        }
        $perPage = min($request->get('per_page', 20), 100);
        $items = $query->paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => DetalleAdicionalResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    public function show($id): JsonResponse
    {
        $item = DetalleAdicional::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => new DetalleAdicionalResource($item),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'detalle_pedido_id' => ['required', 'integer', 'exists:detalle_pedidos,id'],
            'adicional_id' => ['required', 'integer', 'exists:adicionales,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'precio_unitario' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ]);
        $item = DetalleAdicional::create($data);
        return response()->json([
            'success' => true,
            'data' => new DetalleAdicionalResource($item),
            'message' => 'Detalle adicional creado correctamente',
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $item = DetalleAdicional::findOrFail($id);
        $data = $request->validate([
            'cantidad' => ['sometimes', 'integer', 'min:1'],
            'precio_unitario' => ['sometimes', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ]);
        $item->update($data);
        return response()->json([
            'success' => true,
            'data' => new DetalleAdicionalResource($item),
            'message' => 'Detalle adicional actualizado correctamente',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $item = DetalleAdicional::findOrFail($id);
        $item->delete();
        return response()->json([
            'success' => true,
            'message' => 'Detalle adicional eliminado correctamente',
        ]);
    }
} 