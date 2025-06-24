<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdicionalGrupoResource;
use App\Models\AdicionalGrupo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class AdicionalGrupoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AdicionalGrupo::query();
        if ($request->filled('adicional_id')) {
            $query->where('adicional_id', $request->adicional_id);
        }
        if ($request->filled('grupo_adicional_id')) {
            $query->where('grupo_adicional_id', $request->grupo_adicional_id);
        }
        $perPage = min($request->get('per_page', 20), 100);
        $items = $query->paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => AdicionalGrupoResource::collection($items->items()),
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
        $item = AdicionalGrupo::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => new AdicionalGrupoResource($item),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'adicional_id' => ['required', 'integer', 'exists:adicionales,id'],
            'grupo_adicional_id' => ['required', 'integer', 'exists:grupos_adicionales,id'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);
        $item = AdicionalGrupo::create($data);
        return response()->json([
            'success' => true,
            'data' => new AdicionalGrupoResource($item),
            'message' => 'Relación adicional-grupo creada correctamente',
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $item = AdicionalGrupo::findOrFail($id);
        $data = $request->validate([
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);
        $item->update($data);
        return response()->json([
            'success' => true,
            'data' => new AdicionalGrupoResource($item),
            'message' => 'Relación adicional-grupo actualizada correctamente',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $item = AdicionalGrupo::findOrFail($id);
        $item->delete();
        return response()->json([
            'success' => true,
            'message' => 'Relación adicional-grupo eliminada correctamente',
        ]);
    }
} 