<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Atributo;
use App\Http\Requests\StoreAtributoRequest;
use App\Http\Requests\UpdateAtributoRequest;
use App\Http\Resources\AtributoResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class AtributoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 10);
            $query = Atributo::query();

            if ($request->has('nombre')) {
                $query->where('nombre', 'like', '%' . $request->query('nombre') . '%');
            }

            if ($request->has('tipo')) {
                $query->where('tipo', $request->query('tipo'));
            }

            if ($request->has('filtrable')) {
                $query->where('filtrable', filter_var($request->query('filtrable'), FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->has('visible')) {
                $query->where('visible', filter_var($request->query('visible'), FILTER_VALIDATE_BOOLEAN));
            }
            
            $atributos = $query->with('valores')->orderBy('nombre', 'asc')->paginate($perPage);
            
            return AtributoResource::collection($atributos)->response()->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al obtener atributos: " . $e->getMessage());
            return response()->json(['message' => 'Error interno del servidor al obtener atributos.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAtributoRequest $request): JsonResponse
    {
        try {
            $datosValidados = $request->validated();
            $atributo = Atributo::create($datosValidados);
            return (new AtributoResource($atributo->load('valores')))
                ->response()
                ->setStatusCode(201);
        } catch (Exception $e) {
            Log::error("Error al crear atributo: " . $e->getMessage());
            return response()->json(['message' => 'Error interno del servidor al crear el atributo.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Atributo $atributo): JsonResponse
    {
        try {
            return (new AtributoResource($atributo->load('valores')))
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al obtener atributo ID {$atributo->id}: " . $e->getMessage());
            return response()->json(['message' => 'Error interno del servidor al obtener el atributo.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAtributoRequest $request, Atributo $atributo): JsonResponse
    {
        try {
            $datosValidados = $request->validated();
            $atributo->update($datosValidados);
            return (new AtributoResource($atributo->load('valores')))
                ->response()
                ->setStatusCode(200);
        } catch (Exception $e) {
            Log::error("Error al actualizar atributo ID {$atributo->id}: " . $e->getMessage());
            return response()->json(['message' => 'Error interno del servidor al actualizar el atributo.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Atributo $atributo): JsonResponse
    {
        try {
            // Considerar validación: no eliminar si tiene valores asociados o productos que lo usan.
            if ($atributo->valores()->exists()) {
                 return response()->json(['message' => 'No se puede eliminar el atributo porque tiene valores asociados.'], 409); // Conflict
            }
            $atributo->delete();
            return response()->json(null, 204);
        } catch (Exception $e) {
            Log::error("Error al eliminar atributo ID {$atributo->id}: " . $e->getMessage());
            return response()->json(['message' => 'Error interno del servidor al eliminar el atributo.', 'error' => $e->getMessage()], 500);
        }
    }
} 