<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HorarioZonaResource;
use App\Models\HorarioZona;
use App\Models\ZonaReparto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class HorarioZonaController extends Controller
{
    private const DIAS_SEMANA = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    /**
     * Obtener listado de horarios de zona
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $query = HorarioZona::query()
                ->with(['zonaReparto']);

            // Filtros
            if ($request->has('zona_reparto_id')) {
                $query->where('zona_reparto_id', $request->zona_reparto_id);
            }

            if ($request->has('dia_semana')) {
                $query->where('dia_semana', $request->dia_semana);
            }

            if ($request->boolean('activo', true)) {
                $query->where('activo', true);
            }

            if ($request->boolean('dia_completo')) {
                $query->where('dia_completo', true);
            }

            // Ordenamiento por día de la semana y hora de inicio
            $query->orderByRaw("FIELD(dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo')")
                ->orderBy('hora_inicio');

            $horarios = $query->paginate($request->input('per_page', 15));

            return HorarioZonaResource::collection($horarios);
        } catch (\Exception $e) {
            Log::error('Error al obtener horarios de zona: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crear nuevo horario de zona
     */
    public function store(Request $request): HorarioZonaResource
    {
        try {
            $validated = $request->validate([
                'zona_reparto_id' => 'required|exists:zonas_reparto,id',
                'dia_semana' => 'required|in:' . implode(',', self::DIAS_SEMANA),
                'hora_inicio' => 'required_unless:dia_completo,true|date_format:H:i:s|nullable',
                'hora_fin' => 'required_unless:dia_completo,true|date_format:H:i:s|nullable|after:hora_inicio',
                'activo' => 'boolean',
                'dia_completo' => 'boolean',
                'observaciones' => 'nullable|string|max:500',
            ]);

            // Verificar si ya existe un horario para ese día en esa zona
            $existente = HorarioZona::where('zona_reparto_id', $validated['zona_reparto_id'])
                ->where('dia_semana', $validated['dia_semana'])
                ->first();

            if ($existente) {
                throw ValidationException::withMessages([
                    'dia_semana' => ['Ya existe un horario para este día en esta zona de reparto.']
                ]);
            }

            DB::beginTransaction();
            
            $horario = HorarioZona::create($validated);
            
            // Cargar relaciones necesarias
            $horario->load(['zonaReparto']);
            
            DB::commit();

            return new HorarioZonaResource($horario);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear horario de zona: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener un horario de zona específico
     */
    public function show(int $id): HorarioZonaResource
    {
        try {
            $horario = HorarioZona::with(['zonaReparto'])
                ->findOrFail($id);

            return new HorarioZonaResource($horario);
        } catch (\Exception $e) {
            Log::error('Error al obtener horario de zona: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar un horario de zona
     */
    public function update(Request $request, int $id): HorarioZonaResource
    {
        try {
            $horario = HorarioZona::findOrFail($id);

            $validated = $request->validate([
                'hora_inicio' => 'required_unless:dia_completo,true|date_format:H:i:s|nullable',
                'hora_fin' => 'required_unless:dia_completo,true|date_format:H:i:s|nullable|after:hora_inicio',
                'activo' => 'boolean',
                'dia_completo' => 'boolean',
                'observaciones' => 'nullable|string|max:500',
            ]);

            DB::beginTransaction();
            
            $horario->update($validated);
            
            // Cargar relaciones necesarias
            $horario->load(['zonaReparto']);
            
            DB::commit();

            return new HorarioZonaResource($horario);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar horario de zona: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar un horario de zona
     */
    public function destroy(int $id): Response
    {
        try {
            $horario = HorarioZona::findOrFail($id);

            DB::beginTransaction();
            
            $horario->delete();
            
            DB::commit();

            return response()->noContent();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar horario de zona: ' . $e->getMessage());
            throw $e;
        }
    }
} 