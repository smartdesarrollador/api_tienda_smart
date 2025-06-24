<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GrupoAdicionalResource;
use App\Models\GrupoAdicional;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;

class GrupoAdicionalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = GrupoAdicional::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%$search%")
                  ->orWhere('descripcion', 'like', "%$search%")
                  ->orWhere('slug', 'like', "%$search%")
                  ;
            });
        }
        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }
        $perPage = min($request->get('per_page', 20), 100);
        $grupos = $query->orderBy('orden')->paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => GrupoAdicionalResource::collection($grupos->items()),
            'meta' => [
                'current_page' => $grupos->currentPage(),
                'last_page' => $grupos->lastPage(),
                'per_page' => $grupos->perPage(),
                'total' => $grupos->total(),
                'from' => $grupos->firstItem(),
                'to' => $grupos->lastItem(),
            ],
        ]);
    }

    public function show(GrupoAdicional $grupoAdicional): JsonResponse
    {
        $grupoAdicional->load(['adicionales', 'productos']);
        return response()->json([
            'success' => true,
            'data' => new GrupoAdicionalResource($grupoAdicional),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:grupos_adicionales,slug'],
            'descripcion' => ['nullable', 'string'],
            'obligatorio' => ['boolean'],
            'multiple_seleccion' => ['boolean'],
            'minimo_selecciones' => ['nullable', 'integer', 'min:0'],
            'maximo_selecciones' => ['nullable', 'integer', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ]);
        $grupo = GrupoAdicional::create($data);
        return response()->json([
            'success' => true,
            'data' => new GrupoAdicionalResource($grupo),
            'message' => 'Grupo adicional creado correctamente',
        ], 201);
    }

    public function update(Request $request, GrupoAdicional $grupoAdicional): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('grupos_adicionales', 'slug')->ignore($grupoAdicional->id)],
            'descripcion' => ['nullable', 'string'],
            'obligatorio' => ['boolean'],
            'multiple_seleccion' => ['boolean'],
            'minimo_selecciones' => ['nullable', 'integer', 'min:0'],
            'maximo_selecciones' => ['nullable', 'integer', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ]);
        $grupoAdicional->update($data);
        return response()->json([
            'success' => true,
            'data' => new GrupoAdicionalResource($grupoAdicional),
            'message' => 'Grupo adicional actualizado correctamente',
        ]);
    }

    public function destroy(GrupoAdicional $grupoAdicional): JsonResponse
    {
        $grupoAdicional->delete();
        return response()->json([
            'success' => true,
            'message' => 'Grupo adicional eliminado correctamente',
        ]);
    }
} 