<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\StoreCuponRequest;
use App\Http\Requests\API\UpdateCuponRequest;
use App\Http\Resources\CuponResource;
use App\Models\Cupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class CuponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Cupon::query();

            // Filtro por código
            if ($request->has('codigo')) {
                $query->where('codigo', 'like', '%' . $request->input('codigo') . '%');
            }

            // Filtro por tipo
            if ($request->has('tipo')) {
                $query->where('tipo', $request->input('tipo'));
            }

            // Filtro por activo
            if ($request->has('activo')) {
                $query->where('activo', filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN));
            }
            
            // Filtro por vigencia (esta_vigente, puede_usarse)
            if ($request->has('estado_vigencia')) {
                match ($request->input('estado_vigencia')) {
                    'vigente' => $query->vigentes()->activos(),
                    'no_vigente' => $query->where(function($q) {
                        $q->where('fecha_fin', '< ', now())->orWhere('activo', false);
                    }),
                    'proximo' => $query->where('fecha_inicio', '>', now())->activos(),
                    'usado_completamente' => $query->whereNotNull('limite_uso')->whereColumn('usos', '>=', 'limite_uso'),
                    default => null,
                };
            }

            // Ordenación
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $cupones = $query->paginate($request->input('per_page', 15));

            return CuponResource::collection($cupones)->response();
        } catch (Exception $e) {
            Log::error("Error al obtener listado de cupones: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno al procesar la solicitud.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreCuponRequest $request): JsonResponse
    {
        try {
            $datosValidados = $request->validated();
            $cupon = Cupon::create($datosValidados);
            return (new CuponResource($cupon))
                ->response()
                ->setStatusCode(201);
        } catch (Exception $e) {
            Log::error("Error al crear cupón: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno al crear el cupón.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Cupon $cupon): JsonResponse
    {
        try {
            return (new CuponResource($cupon))->response();
        } catch (Exception $e) {
            Log::error("Error al mostrar cupón ID {$cupon->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno al obtener el cupón.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateCuponRequest $request, Cupon $cupon): JsonResponse
    {
        try {
            $datosValidados = $request->validated();
            $cupon->update($datosValidados);
            return (new CuponResource($cupon->fresh()))->response();
        } catch (Exception $e) {
            Log::error("Error al actualizar cupón ID {$cupon->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno al actualizar el cupón.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Cupon $cupon): JsonResponse
    {
        try {
            // Lógica adicional: Verificar si el cupón está asociado a pedidos, etc.
            // Por ahora, simplemente lo eliminamos.
            // if ($cupon->pedidos()->exists()) { // Asumiendo una relación `pedidos()`
            //     return response()->json(['message' => 'No se puede eliminar el cupón porque está asociado a pedidos.'], 409);
            // }
            $cupon->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            Log::error("Error al eliminar cupón ID {$cupon->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno al eliminar el cupón.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 