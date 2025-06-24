<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdicionalResource;
use App\Models\Adicional;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;

class AdicionalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Adicional::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%$search%")
                  ->orWhere('descripcion', 'like', "%$search%")
                  ->orWhere('slug', 'like', "%$search%")
                  ->orWhere('tipo', 'like', "%$search%")
                  ->orWhere('alergenos', 'like', "%$search%")
                  ->orWhere('informacion_nutricional', 'like', "%$search%")
                  ;
            });
        }
        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }
        if ($request->filled('disponible')) {
            $query->where('disponible', $request->boolean('disponible'));
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        $perPage = min($request->get('per_page', 20), 100);
        $adicionales = $query->orderBy('orden')->paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => AdicionalResource::collection($adicionales->items()),
            'meta' => [
                'current_page' => $adicionales->currentPage(),
                'last_page' => $adicionales->lastPage(),
                'per_page' => $adicionales->perPage(),
                'total' => $adicionales->total(),
                'from' => $adicionales->firstItem(),
                'to' => $adicionales->lastItem(),
            ],
        ]);
    }

    public function show(Adicional $adicional): JsonResponse
    {
        $adicional->load(['productos', 'gruposAdicionales']);
        return response()->json([
            'success' => true,
            'data' => new AdicionalResource($adicional),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:adicionales,slug'],
            'descripcion' => ['nullable', 'string'],
            'precio' => ['required', 'numeric', 'min:0'],
            'imagen' => ['nullable', 'string', 'max:255'],
            'icono' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'max:50'],
            'disponible' => ['boolean'],
            'activo' => ['boolean'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'tiempo_preparacion' => ['nullable', 'integer', 'min:0'],
            'calorias' => ['nullable', 'numeric', 'min:0'],
            'informacion_nutricional' => ['nullable', 'array'],
            'alergenos' => ['nullable', 'array'],
            'vegetariano' => ['boolean'],
            'vegano' => ['boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);
        $adicional = Adicional::create($data);
        return response()->json([
            'success' => true,
            'data' => new AdicionalResource($adicional),
            'message' => 'Adicional creado correctamente',
        ], 201);
    }

    public function update(Request $request, Adicional $adicional): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('adicionales', 'slug')->ignore($adicional->id)],
            'descripcion' => ['nullable', 'string'],
            'precio' => ['sometimes', 'numeric', 'min:0'],
            'imagen' => ['nullable', 'string', 'max:255'],
            'icono' => ['nullable', 'string', 'max:255'],
            'tipo' => ['sometimes', 'string', 'max:50'],
            'disponible' => ['boolean'],
            'activo' => ['boolean'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'tiempo_preparacion' => ['nullable', 'integer', 'min:0'],
            'calorias' => ['nullable', 'numeric', 'min:0'],
            'informacion_nutricional' => ['nullable', 'array'],
            'alergenos' => ['nullable', 'array'],
            'vegetariano' => ['boolean'],
            'vegano' => ['boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);
        $adicional->update($data);
        return response()->json([
            'success' => true,
            'data' => new AdicionalResource($adicional),
            'message' => 'Adicional actualizado correctamente',
        ]);
    }

    public function destroy(Adicional $adicional): JsonResponse
    {
        $adicional->delete();
        return response()->json([
            'success' => true,
            'message' => 'Adicional eliminado correctamente',
        ]);
    }
} 