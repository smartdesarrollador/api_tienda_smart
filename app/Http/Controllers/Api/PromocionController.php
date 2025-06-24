<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\PromocionResource;
use App\Models\Promocion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PromocionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $promociones = Promocion::query()
            ->when($request->activo, fn($q) => $q->where('activo', $request->activo))
            ->when($request->tipo, fn($q) => $q->where('tipo', $request->tipo))
            ->when($request->vigente, function($q) {
                return $q->where('activo', true)
                        ->where(function($query) {
                            $query->whereNull('fecha_inicio')
                                  ->orWhere('fecha_inicio', '<=', now());
                        })
                        ->where(function($query) {
                            $query->whereNull('fecha_fin')
                                  ->orWhere('fecha_fin', '>=', now());
                        });
            })
            ->when($request->search, fn($q) => $q->where('nombre', 'like', '%' . $request->search . '%'))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => PromocionResource::collection($promociones),
            'meta' => [
                'total' => $promociones->total(),
                'per_page' => $promociones->perPage(),
                'current_page' => $promociones->currentPage(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'tipo' => 'required|in:descuento_producto,descuento_categoria,2x1,3x2,envio_gratis,combo',
            'descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'descuento_monto' => 'nullable|numeric|min:0',
            'compra_minima' => 'nullable|numeric|min:0',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'productos_incluidos' => 'nullable|array',
            'categorias_incluidas' => 'nullable|array',
            'zonas_aplicables' => 'nullable|array',
            'limite_uso_total' => 'nullable|integer|min:1',
            'limite_uso_cliente' => 'nullable|integer|min:1',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $promocion = Promocion::create([
                'nombre' => $request->nombre,
                'slug' => Str::slug($request->nombre),
                'descripcion' => $request->descripcion,
                'tipo' => $request->tipo,
                'descuento_porcentaje' => $request->descuento_porcentaje,
                'descuento_monto' => $request->descuento_monto,
                'compra_minima' => $request->compra_minima,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'activo' => $request->activo ?? true,
                'productos_incluidos' => $request->productos_incluidos,
                'categorias_incluidas' => $request->categorias_incluidas,
                'zonas_aplicables' => $request->zonas_aplicables,
                'limite_uso_total' => $request->limite_uso_total,
                'limite_uso_cliente' => $request->limite_uso_cliente,
                'usos_actuales' => 0,
            ]);

            if ($request->hasFile('imagen')) {
                $imagen = $request->file('imagen');
                $nombreImagen = time() . '_' . $imagen->getClientOriginalName();
                $imagen->move(public_path('assets/promociones'), $nombreImagen);
                $promocion->imagen = $nombreImagen;
                $promocion->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Promoción creada exitosamente',
                'data' => new PromocionResource($promocion)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la promoción'], 500);
        }
    }

    public function show(Promocion $promocion): JsonResponse
    {
        return response()->json([
            'data' => new PromocionResource($promocion)
        ]);
    }

    public function update(Request $request, Promocion $promocion): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'sometimes|required|string',
            'tipo' => 'sometimes|required|in:descuento_producto,descuento_categoria,2x1,3x2,envio_gratis,combo',
            'descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'descuento_monto' => 'nullable|numeric|min:0',
            'compra_minima' => 'nullable|numeric|min:0',
            'fecha_inicio' => 'sometimes|required|date',
            'fecha_fin' => 'sometimes|required|date|after:fecha_inicio',
            'productos_incluidos' => 'nullable|array',
            'categorias_incluidas' => 'nullable|array',
            'zonas_aplicables' => 'nullable|array',
            'limite_uso_total' => 'nullable|integer|min:1',
            'limite_uso_cliente' => 'nullable|integer|min:1',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $promocion->fill($request->except(['imagen']));
            
            if ($request->has('nombre')) {
                $promocion->slug = Str::slug($request->nombre);
            }

            if ($request->hasFile('imagen')) {
                // Eliminar imagen anterior si existe
                if ($promocion->imagen && file_exists(public_path('assets/promociones/' . $promocion->imagen))) {
                    unlink(public_path('assets/promociones/' . $promocion->imagen));
                }

                $imagen = $request->file('imagen');
                $nombreImagen = time() . '_' . $imagen->getClientOriginalName();
                $imagen->move(public_path('assets/promociones'), $nombreImagen);
                $promocion->imagen = $nombreImagen;
            }

            $promocion->save();
            DB::commit();

            return response()->json([
                'message' => 'Promoción actualizada exitosamente',
                'data' => new PromocionResource($promocion)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar la promoción'], 500);
        }
    }

    public function destroy(Promocion $promocion): JsonResponse
    {
        try {
            // Eliminar imagen si existe
            if ($promocion->imagen && file_exists(public_path('assets/promociones/' . $promocion->imagen))) {
                unlink(public_path('assets/promociones/' . $promocion->imagen));
            }

            $promocion->delete();
            return response()->json(['message' => 'Promoción eliminada exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la promoción'], 500);
        }
    }

    public function toggleActivo(Promocion $promocion): JsonResponse
    {
        try {
            $promocion->activo = !$promocion->activo;
            $promocion->save();

            $estado = $promocion->activo ? 'activada' : 'desactivada';
            
            return response()->json([
                'message' => "Promoción {$estado} exitosamente",
                'data' => new PromocionResource($promocion)
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cambiar el estado de la promoción'], 500);
        }
    }

    public function aplicarPromocion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'promocion_id' => 'required|exists:promociones,id',
            'monto_compra' => 'required|numeric|min:0',
            'productos' => 'nullable|array',
            'zona_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $promocion = Promocion::findOrFail($request->promocion_id);
            
            // Validar si la promoción es aplicable
            $esAplicable = $this->validarPromocionAplicable($promocion, $request);
            
            if (!$esAplicable['aplicable']) {
                return response()->json(['message' => $esAplicable['mensaje']], 400);
            }

            $descuento = $this->calcularDescuento($promocion, $request->monto_compra);

            return response()->json([
                'aplicable' => true,
                'descuento' => $descuento,
                'monto_final' => $request->monto_compra - $descuento,
                'promocion' => new PromocionResource($promocion)
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al aplicar la promoción'], 500);
        }
    }

    private function validarPromocionAplicable(Promocion $promocion, Request $request): array
    {
        if (!$promocion->activo) {
            return ['aplicable' => false, 'mensaje' => 'La promoción no está activa'];
        }

        if (!$promocion->esVigente()) {
            return ['aplicable' => false, 'mensaje' => 'La promoción no está vigente'];
        }

        if ($promocion->compra_minima && $request->monto_compra < $promocion->compra_minima) {
            return ['aplicable' => false, 'mensaje' => 'No cumple con el monto mínimo de compra'];
        }

        if ($promocion->estaAgotada()) {
            return ['aplicable' => false, 'mensaje' => 'La promoción ha alcanzado su límite de uso'];
        }

        return ['aplicable' => true, 'mensaje' => 'Promoción aplicable'];
    }

    private function calcularDescuento(Promocion $promocion, float $montoCompra): float
    {
        if ($promocion->descuento_porcentaje) {
            return ($montoCompra * $promocion->descuento_porcentaje) / 100;
        }

        if ($promocion->descuento_monto) {
            return min($promocion->descuento_monto, $montoCompra);
        }

        return 0.0;
    }
} 