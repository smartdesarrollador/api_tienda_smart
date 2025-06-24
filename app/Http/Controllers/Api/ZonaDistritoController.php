<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ZonaDistritoResource;
use App\Models\ZonaDistrito;
use App\Models\ZonaReparto;
use App\Models\Distrito;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ZonaDistritoController extends Controller
{
    /**
     * Obtener listado de asignaciones zona-distrito
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $query = ZonaDistrito::query()
                ->with(['zonaReparto', 'distrito.provincia.departamento']);

            // Filtros
            if ($request->has('zona_reparto_id')) {
                $query->where('zona_reparto_id', $request->zona_reparto_id);
            }

            if ($request->has('distrito_id')) {
                $query->where('distrito_id', $request->distrito_id);
            }

            if ($request->boolean('activo', true)) {
                $query->where('activo', true);
            }

            // Ordenamiento
            $query->orderBy('prioridad')->orderBy('id');

            $zonasDistritos = $query->paginate($request->input('per_page', 15));

            return ZonaDistritoResource::collection($zonasDistritos);
        } catch (\Exception $e) {
            Log::error('Error al obtener zonas-distrito: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crear nueva asignación zona-distrito
     */
    public function store(Request $request): ZonaDistritoResource
    {
        try {
            $validated = $request->validate([
                'zona_reparto_id' => 'required|exists:zonas_reparto,id',
                'distrito_id' => 'required|exists:distritos,id',
                'costo_envio_personalizado' => 'nullable|numeric|min:0',
                'tiempo_adicional' => 'nullable|integer|min:0',
                'activo' => 'boolean',
                'prioridad' => 'required|integer|min:1|max:3',
            ]);

            // Verificar si ya existe la asignación
            $existente = ZonaDistrito::where('zona_reparto_id', $validated['zona_reparto_id'])
                ->where('distrito_id', $validated['distrito_id'])
                ->first();

            if ($existente) {
                throw ValidationException::withMessages([
                    'distrito_id' => ['El distrito ya está asignado a esta zona de reparto.']
                ]);
            }

            DB::beginTransaction();
            
            $zonaDistrito = ZonaDistrito::create($validated);
            
            // Cargar relaciones necesarias
            $zonaDistrito->load(['zonaReparto', 'distrito.provincia.departamento']);
            
            DB::commit();

            return new ZonaDistritoResource($zonaDistrito);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear zona-distrito: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener una asignación zona-distrito específica
     */
    public function show(int $id): ZonaDistritoResource
    {
        try {
            $zonaDistrito = ZonaDistrito::with(['zonaReparto', 'distrito.provincia.departamento'])
                ->findOrFail($id);

            return new ZonaDistritoResource($zonaDistrito);
        } catch (\Exception $e) {
            Log::error('Error al obtener zona-distrito: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar una asignación zona-distrito
     */
    public function update(Request $request, int $id): ZonaDistritoResource
    {
        try {
            $zonaDistrito = ZonaDistrito::findOrFail($id);

            $validated = $request->validate([
                'costo_envio_personalizado' => 'nullable|numeric|min:0',
                'tiempo_adicional' => 'nullable|integer|min:0',
                'activo' => 'boolean',
                'prioridad' => 'integer|min:1|max:3',
            ]);

            DB::beginTransaction();
            
            $zonaDistrito->update($validated);
            
            // Cargar relaciones necesarias
            $zonaDistrito->load(['zonaReparto', 'distrito.provincia.departamento']);
            
            DB::commit();

            return new ZonaDistritoResource($zonaDistrito);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar zona-distrito: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar una asignación zona-distrito
     */
    public function destroy(int $id): Response
    {
        try {
            $zonaDistrito = ZonaDistrito::findOrFail($id);

            DB::beginTransaction();
            
            $zonaDistrito->delete();
            
            DB::commit();

            return response()->noContent();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar zona-distrito: ' . $e->getMessage());
            throw $e;
        }
    }
} 