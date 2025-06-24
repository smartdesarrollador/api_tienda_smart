<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExcepcionZonaResource;
use App\Models\ExcepcionZona;
use App\Models\ZonaReparto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ExcepcionZonaController extends Controller
{
    private const TIPOS_EXCEPCION = [
        'no_disponible',
        'horario_especial',
        'costo_especial',
        'tiempo_especial'
    ];

    /**
     * Obtener listado de excepciones de zona
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $query = ExcepcionZona::query()
                ->with(['zonaReparto']);

            // Filtros
            if ($request->has('zona_reparto_id')) {
                $query->where('zona_reparto_id', $request->zona_reparto_id);
            }

            if ($request->has('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            if ($request->has('fecha_desde')) {
                $query->where('fecha_excepcion', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->where('fecha_excepcion', '<=', $request->fecha_hasta);
            }

            if ($request->boolean('activo', true)) {
                $query->where('activo', true);
            }

            if ($request->boolean('vigentes')) {
                $query->where('fecha_excepcion', '>=', now()->format('Y-m-d'));
            }

            // Ordenamiento por fecha de excepción
            $query->orderBy('fecha_excepcion')->orderBy('hora_inicio');

            $excepciones = $query->paginate($request->input('per_page', 15));

            return ExcepcionZonaResource::collection($excepciones);
        } catch (\Exception $e) {
            Log::error('Error al obtener excepciones de zona: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crear nueva excepción de zona
     */
    public function store(Request $request): ExcepcionZonaResource
    {
        try {
            $validated = $request->validate([
                'zona_reparto_id' => 'required|exists:zonas_reparto,id',
                'fecha_excepcion' => 'required|date|after_or_equal:today',
                'tipo' => 'required|in:' . implode(',', self::TIPOS_EXCEPCION),
                'hora_inicio' => 'required_if:tipo,horario_especial|date_format:H:i:s|nullable',
                'hora_fin' => 'required_if:tipo,horario_especial|date_format:H:i:s|nullable|after:hora_inicio',
                'costo_especial' => 'required_if:tipo,costo_especial|numeric|min:0|nullable',
                'tiempo_especial_min' => 'required_if:tipo,tiempo_especial|integer|min:0|nullable',
                'tiempo_especial_max' => 'required_if:tipo,tiempo_especial|integer|min:0|nullable|gte:tiempo_especial_min',
                'motivo' => 'required|string|max:500',
                'activo' => 'boolean',
            ]);

            // Verificar si ya existe una excepción para esa fecha y zona
            $existente = ExcepcionZona::where('zona_reparto_id', $validated['zona_reparto_id'])
                ->where('fecha_excepcion', $validated['fecha_excepcion'])
                ->where('tipo', $validated['tipo'])
                ->first();

            if ($existente) {
                throw ValidationException::withMessages([
                    'fecha_excepcion' => ['Ya existe una excepción de este tipo para esta fecha en esta zona.']
                ]);
            }

            DB::beginTransaction();
            
            $excepcion = ExcepcionZona::create($validated);
            
            // Cargar relaciones necesarias
            $excepcion->load(['zonaReparto']);
            
            DB::commit();

            return new ExcepcionZonaResource($excepcion);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear excepción de zona: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener una excepción de zona específica
     */
    public function show(int $id): ExcepcionZonaResource
    {
        try {
            $excepcion = ExcepcionZona::with(['zonaReparto'])
                ->findOrFail($id);

            return new ExcepcionZonaResource($excepcion);
        } catch (\Exception $e) {
            Log::error('Error al obtener excepción de zona: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar una excepción de zona
     */
    public function update(Request $request, int $id): ExcepcionZonaResource
    {
        try {
            $excepcion = ExcepcionZona::findOrFail($id);

            $validated = $request->validate([
                'fecha_excepcion' => 'date|after_or_equal:today',
                'tipo' => 'in:' . implode(',', self::TIPOS_EXCEPCION),
                'hora_inicio' => 'required_if:tipo,horario_especial|date_format:H:i:s|nullable',
                'hora_fin' => 'required_if:tipo,horario_especial|date_format:H:i:s|nullable|after:hora_inicio',
                'costo_especial' => 'required_if:tipo,costo_especial|numeric|min:0|nullable',
                'tiempo_especial_min' => 'required_if:tipo,tiempo_especial|integer|min:0|nullable',
                'tiempo_especial_max' => 'required_if:tipo,tiempo_especial|integer|min:0|nullable|gte:tiempo_especial_min',
                'motivo' => 'string|max:500',
                'activo' => 'boolean',
            ]);

            // Verificar duplicados solo si se cambia la fecha o el tipo
            if (isset($validated['fecha_excepcion']) || isset($validated['tipo'])) {
                $existente = ExcepcionZona::where('zona_reparto_id', $excepcion->zona_reparto_id)
                    ->where('fecha_excepcion', $validated['fecha_excepcion'] ?? $excepcion->fecha_excepcion)
                    ->where('tipo', $validated['tipo'] ?? $excepcion->tipo)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existente) {
                    throw ValidationException::withMessages([
                        'fecha_excepcion' => ['Ya existe una excepción de este tipo para esta fecha en esta zona.']
                    ]);
                }
            }

            DB::beginTransaction();
            
            $excepcion->update($validated);
            
            // Cargar relaciones necesarias
            $excepcion->load(['zonaReparto']);
            
            DB::commit();

            return new ExcepcionZonaResource($excepcion);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar excepción de zona: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar una excepción de zona
     */
    public function destroy(int $id): Response
    {
        try {
            $excepcion = ExcepcionZona::findOrFail($id);

            DB::beginTransaction();
            
            $excepcion->delete();
            
            DB::commit();

            return response()->noContent();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar excepción de zona: ' . $e->getMessage());
            throw $e;
        }
    }
} 