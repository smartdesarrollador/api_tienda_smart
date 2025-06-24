<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CostoEnvioDinamicoResource;
use App\Models\CostoEnvioDinamico;
use App\Models\ZonaReparto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CostoEnvioDinamicoController extends Controller
{
    /**
     * Obtener listado de costos de envío dinámicos
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $query = CostoEnvioDinamico::query()
                ->with(['zonaReparto']);

            // Filtros
            if ($request->has('zona_reparto_id')) {
                $query->where('zona_reparto_id', $request->zona_reparto_id);
            }

            if ($request->has('distancia')) {
                $distancia = (float) $request->distancia;
                $query->where('distancia_desde_km', '<=', $distancia)
                    ->where('distancia_hasta_km', '>', $distancia);
            }

            if ($request->boolean('activo', true)) {
                $query->where('activo', true);
            }

            // Ordenamiento por rango de distancia
            $query->orderBy('distancia_desde_km');

            $costos = $query->paginate($request->input('per_page', 15));

            return CostoEnvioDinamicoResource::collection($costos);
        } catch (\Exception $e) {
            Log::error('Error al obtener costos de envío dinámicos: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crear nuevo costo de envío dinámico
     */
    public function store(Request $request): CostoEnvioDinamicoResource
    {
        try {
            $validated = $request->validate([
                'zona_reparto_id' => 'required|exists:zonas_reparto,id',
                'distancia_desde_km' => 'required|numeric|min:0',
                'distancia_hasta_km' => 'required|numeric|gt:distancia_desde_km',
                'costo_envio' => 'required|numeric|min:0',
                'tiempo_adicional' => 'nullable|numeric|min:0',
                'activo' => 'boolean',
            ]);

            // Verificar si hay solapamiento de rangos
            $solapamiento = CostoEnvioDinamico::where('zona_reparto_id', $validated['zona_reparto_id'])
                ->where(function ($query) use ($validated) {
                    $query->where(function ($q) use ($validated) {
                        $q->where('distancia_desde_km', '<=', $validated['distancia_desde_km'])
                            ->where('distancia_hasta_km', '>', $validated['distancia_desde_km']);
                    })->orWhere(function ($q) use ($validated) {
                        $q->where('distancia_desde_km', '<', $validated['distancia_hasta_km'])
                            ->where('distancia_hasta_km', '>=', $validated['distancia_hasta_km']);
                    });
                })->first();

            if ($solapamiento) {
                throw ValidationException::withMessages([
                    'distancia_desde_km' => ['El rango de distancia se solapa con otro existente.']
                ]);
            }

            DB::beginTransaction();
            
            $costo = CostoEnvioDinamico::create($validated);
            
            // Cargar relaciones necesarias
            $costo->load(['zonaReparto']);
            
            DB::commit();

            return new CostoEnvioDinamicoResource($costo);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear costo de envío dinámico: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener un costo de envío dinámico específico
     */
    public function show(int $id): CostoEnvioDinamicoResource
    {
        try {
            $costo = CostoEnvioDinamico::with(['zonaReparto'])
                ->findOrFail($id);

            return new CostoEnvioDinamicoResource($costo);
        } catch (\Exception $e) {
            Log::error('Error al obtener costo de envío dinámico: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar un costo de envío dinámico
     */
    public function update(Request $request, int $id): CostoEnvioDinamicoResource
    {
        try {
            $costo = CostoEnvioDinamico::findOrFail($id);

            $validated = $request->validate([
                'distancia_desde_km' => 'numeric|min:0',
                'distancia_hasta_km' => 'numeric|gt:distancia_desde_km',
                'costo_envio' => 'numeric|min:0',
                'tiempo_adicional' => 'nullable|numeric|min:0',
                'activo' => 'boolean',
            ]);

            // Verificar solapamiento solo si se modifican las distancias
            if (isset($validated['distancia_desde_km']) || isset($validated['distancia_hasta_km'])) {
                $solapamiento = CostoEnvioDinamico::where('zona_reparto_id', $costo->zona_reparto_id)
                    ->where('id', '!=', $id)
                    ->where(function ($query) use ($validated, $costo) {
                        $desde = $validated['distancia_desde_km'] ?? $costo->distancia_desde_km;
                        $hasta = $validated['distancia_hasta_km'] ?? $costo->distancia_hasta_km;
                        
                        $query->where(function ($q) use ($desde) {
                            $q->where('distancia_desde_km', '<=', $desde)
                                ->where('distancia_hasta_km', '>', $desde);
                        })->orWhere(function ($q) use ($hasta) {
                            $q->where('distancia_desde_km', '<', $hasta)
                                ->where('distancia_hasta_km', '>=', $hasta);
                        });
                    })->first();

                if ($solapamiento) {
                    throw ValidationException::withMessages([
                        'distancia_desde_km' => ['El rango de distancia se solapa con otro existente.']
                    ]);
                }
            }

            DB::beginTransaction();
            
            $costo->update($validated);
            
            // Cargar relaciones necesarias
            $costo->load(['zonaReparto']);
            
            DB::commit();

            return new CostoEnvioDinamicoResource($costo);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar costo de envío dinámico: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar un costo de envío dinámico
     */
    public function destroy(int $id): Response
    {
        try {
            $costo = CostoEnvioDinamico::findOrFail($id);

            DB::beginTransaction();
            
            $costo->delete();
            
            DB::commit();

            return response()->noContent();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar costo de envío dinámico: ' . $e->getMessage());
            throw $e;
        }
    }
} 