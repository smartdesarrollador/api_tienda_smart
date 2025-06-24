<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductoAdicionalResource;
use App\Models\ProductoAdicional;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductoAdicionalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductoAdicional::query();
        if ($request->filled('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }
        if ($request->filled('adicional_id')) {
            $query->where('adicional_id', $request->adicional_id);
        }
        $perPage = min($request->get('per_page', 20), 100);
        $items = $query->paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => ProductoAdicionalResource::collection($items->items()),
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
        $item = ProductoAdicional::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => new ProductoAdicionalResource($item),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'adicional_id' => ['required', 'integer', 'exists:adicionales,id'],
            'precio_personalizado' => ['nullable', 'numeric', 'min:0'],
            'obligatorio' => ['boolean'],
            'maximo_cantidad' => ['nullable', 'integer', 'min:1'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ]);
        $item = ProductoAdicional::create($data);
        return response()->json([
            'success' => true,
            'data' => new ProductoAdicionalResource($item),
            'message' => 'Producto adicional creado correctamente',
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $item = ProductoAdicional::findOrFail($id);
        $data = $request->validate([
            'precio_personalizado' => ['nullable', 'numeric', 'min:0'],
            'obligatorio' => ['boolean'],
            'maximo_cantidad' => ['nullable', 'integer', 'min:1'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ]);
        $item->update($data);
        return response()->json([
            'success' => true,
            'data' => new ProductoAdicionalResource($item),
            'message' => 'Producto adicional actualizado correctamente',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $item = ProductoAdicional::findOrFail($id);
        $item->delete();
        return response()->json([
            'success' => true,
            'message' => 'Producto adicional eliminado correctamente',
        ]);
    }
} 