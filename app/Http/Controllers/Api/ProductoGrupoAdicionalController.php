<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductoGrupoAdicionalResource;
use App\Models\ProductoGrupoAdicional;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class ProductoGrupoAdicionalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductoGrupoAdicional::query();
        if ($request->filled('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }
        if ($request->filled('grupo_adicional_id')) {
            $query->where('grupo_adicional_id', $request->grupo_adicional_id);
        }
        $perPage = min($request->get('per_page', 20), 100);
        $items = $query->paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => ProductoGrupoAdicionalResource::collection($items->items()),
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
        $item = ProductoGrupoAdicional::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => new ProductoGrupoAdicionalResource($item),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'grupo_adicional_id' => ['required', 'integer', 'exists:grupos_adicionales,id'],
            'obligatorio' => ['boolean'],
            'minimo_selecciones' => ['nullable', 'integer', 'min:0'],
            'maximo_selecciones' => ['nullable', 'integer', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ]);
        $item = ProductoGrupoAdicional::create($data);
        return response()->json([
            'success' => true,
            'data' => new ProductoGrupoAdicionalResource($item),
            'message' => 'Relación producto-grupo adicional creada correctamente',
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $item = ProductoGrupoAdicional::findOrFail($id);
        $data = $request->validate([
            'obligatorio' => ['boolean'],
            'minimo_selecciones' => ['nullable', 'integer', 'min:0'],
            'maximo_selecciones' => ['nullable', 'integer', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ]);
        $item->update($data);
        return response()->json([
            'success' => true,
            'data' => new ProductoGrupoAdicionalResource($item),
            'message' => 'Relación producto-grupo adicional actualizada correctamente',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $item = ProductoGrupoAdicional::findOrFail($id);
        $item->delete();
        return response()->json([
            'success' => true,
            'message' => 'Relación producto-grupo adicional eliminada correctamente',
        ]);
    }
} 