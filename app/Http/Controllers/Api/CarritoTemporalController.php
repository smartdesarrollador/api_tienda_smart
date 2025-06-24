<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarritoTemporalResource;
use App\Models\CarritoTemporal;
use App\Models\Producto;
use App\Models\VariacionProducto;
use App\Models\Adicional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CarritoTemporalController extends Controller
{
    /**
     * Obtener items del carrito temporal
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CarritoTemporal::query()
                ->with(['producto', 'variacion', 'user'])
                ->noExpirado();

            // Filtrar por usuario o sesión
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            } elseif ($request->has('session_id') && $request->session_id) {
                $query->where('session_id', $request->session_id);
            }

            $carritos = $query->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'data' => CarritoTemporalResource::collection($carritos),
                'meta' => [
                    'total' => $carritos->total(),
                    'per_page' => $carritos->perPage(),
                    'current_page' => $carritos->currentPage(),
                    'last_page' => $carritos->lastPage(),
                    'from' => $carritos->firstItem(),
                    'to' => $carritos->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener carrito temporal: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener el carrito temporal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar producto al carrito temporal
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'session_id' => 'nullable|string|max:255',
                'producto_id' => 'required|exists:productos,id',
                'variacion_id' => 'nullable|exists:variaciones_productos,id',
                'cantidad' => 'required|integer|min:1|max:999',
                'adicionales_seleccionados' => 'nullable|array',
                'adicionales_seleccionados.*' => 'array',
                'adicionales_seleccionados.*.cantidad' => 'integer|min:1',
                'adicionales_seleccionados.*.precio' => 'numeric|min:0',
                'observaciones' => 'nullable|string|max:500',
            ]);

            // Validar que se proporcione user_id o session_id
            if (empty($validated['user_id']) && empty($validated['session_id'])) {
                throw ValidationException::withMessages([
                    'identificacion' => ['Debe proporcionar user_id o session_id']
                ]);
            }

            DB::beginTransaction();

            // Verificar si el producto existe y está disponible
            $producto = Producto::with(['variaciones'])->findOrFail($validated['producto_id']);
            
            if (!$producto->activo || !$producto->disponible) {
                throw ValidationException::withMessages([
                    'producto_id' => ['El producto no está disponible']
                ]);
            }

            // Verificar variación si se proporciona
            $variacion = null;
            if (!empty($validated['variacion_id'])) {
                $variacion = VariacionProducto::findOrFail($validated['variacion_id']);
                
                if ($variacion->producto_id !== $producto->id) {
                    throw ValidationException::withMessages([
                        'variacion_id' => ['La variación no pertenece al producto especificado']
                    ]);
                }
                
                if (!$variacion->disponible) {
                    throw ValidationException::withMessages([
                        'variacion_id' => ['La variación no está disponible']
                    ]);
                }
            }

            // Verificar stock
            $stockDisponible = $variacion ? $variacion->stock : $producto->stock;
            if ($stockDisponible !== null && $stockDisponible < $validated['cantidad']) {
                throw ValidationException::withMessages([
                    'cantidad' => ['Stock insuficiente. Disponible: ' . $stockDisponible]
                ]);
            }

            // Calcular precio unitario
            $precioUnitario = $this->calcularPrecioUnitario($producto, $variacion);

            // Verificar si ya existe un item similar en el carrito
            $itemExistente = CarritoTemporal::query()
                ->where('producto_id', $validated['producto_id'])
                ->where('variacion_id', $validated['variacion_id'])
                ->when($validated['user_id'], fn($q) => $q->where('user_id', $validated['user_id']))
                ->when($validated['session_id'], fn($q) => $q->where('session_id', $validated['session_id']))
                ->noExpirado()
                ->first();

            if ($itemExistente) {
                // Actualizar cantidad del item existente
                $nuevaCantidad = $itemExistente->cantidad + $validated['cantidad'];
                
                // Verificar stock para la nueva cantidad
                if ($stockDisponible !== null && $stockDisponible < $nuevaCantidad) {
                    throw ValidationException::withMessages([
                        'cantidad' => ['Stock insuficiente para la cantidad total. Disponible: ' . $stockDisponible . ', en carrito: ' . $itemExistente->cantidad]
                    ]);
                }
                
                $itemExistente->update([
                    'cantidad' => $nuevaCantidad,
                    'precio_unitario' => $precioUnitario,
                    'adicionales_seleccionados' => $validated['adicionales_seleccionados'] ?? null,
                    'observaciones' => $validated['observaciones'] ?? $itemExistente->observaciones,
                    'fecha_expiracion' => now()->addHours(24),
                ]);
                
                $carrito = $itemExistente;
            } else {
                // Crear nuevo item en el carrito
                $carrito = CarritoTemporal::create([
                    'user_id' => $validated['user_id'] ?? null,
                    'session_id' => $validated['session_id'] ?? null,
                    'producto_id' => $validated['producto_id'],
                    'variacion_id' => $validated['variacion_id'] ?? null,
                    'cantidad' => $validated['cantidad'],
                    'precio_unitario' => $precioUnitario,
                    'adicionales_seleccionados' => $validated['adicionales_seleccionados'] ?? null,
                    'observaciones' => $validated['observaciones'] ?? null,
                    'fecha_expiracion' => now()->addHours(24),
                ]);
            }

            $carrito->load(['producto', 'variacion', 'user']);

            DB::commit();

            return response()->json([
                'message' => 'Producto agregado al carrito exitosamente',
                'data' => new CarritoTemporalResource($carrito)
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al agregar producto al carrito: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al agregar producto al carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un item específico del carrito
     */
    public function show(CarritoTemporal $carritoTemporal): JsonResponse
    {
        try {
            $carritoTemporal->load(['producto', 'variacion', 'user']);
            
            return response()->json([
                'data' => new CarritoTemporalResource($carritoTemporal)
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener item del carrito: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener el item del carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar item del carrito
     */
    public function update(Request $request, CarritoTemporal $carritoTemporal): JsonResponse
    {
        try {
            $validated = $request->validate([
                'cantidad' => 'sometimes|required|integer|min:1|max:999',
                'adicionales_seleccionados' => 'nullable|array',
                'adicionales_seleccionados.*' => 'array',
                'adicionales_seleccionados.*.cantidad' => 'integer|min:1',
                'adicionales_seleccionados.*.precio' => 'numeric|min:0',
                'observaciones' => 'nullable|string|max:500',
            ]);

            DB::beginTransaction();

            // Verificar si el carrito no ha expirado
            if ($carritoTemporal->estaExpirado()) {
                throw ValidationException::withMessages([
                    'carrito' => ['El item del carrito ha expirado']
                ]);
            }

            // Si se actualiza la cantidad, verificar stock
            if (isset($validated['cantidad'])) {
                $carritoTemporal->load(['producto', 'variacion']);
                
                $stockDisponible = $carritoTemporal->variacion 
                    ? $carritoTemporal->variacion->stock 
                    : $carritoTemporal->producto->stock;
                
                if ($stockDisponible !== null && $stockDisponible < $validated['cantidad']) {
                    throw ValidationException::withMessages([
                        'cantidad' => ['Stock insuficiente. Disponible: ' . $stockDisponible]
                    ]);
                }
                
                $carritoTemporal->cantidad = $validated['cantidad'];
            }

            if (isset($validated['adicionales_seleccionados'])) {
                $carritoTemporal->adicionales_seleccionados = $validated['adicionales_seleccionados'];
            }

            if (isset($validated['observaciones'])) {
                $carritoTemporal->observaciones = $validated['observaciones'];
            }

            // Actualizar fecha de expiración
            $carritoTemporal->fecha_expiracion = now()->addHours(24);
            $carritoTemporal->save();

            $carritoTemporal->load(['producto', 'variacion', 'user']);

            DB::commit();

            return response()->json([
                'message' => 'Carrito actualizado exitosamente',
                'data' => new CarritoTemporalResource($carritoTemporal)
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar carrito: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar el carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar item del carrito
     */
    public function destroy(CarritoTemporal $carritoTemporal): JsonResponse
    {
        try {
            $carritoTemporal->delete();
            
            return response()->json([
                'message' => 'Producto eliminado del carrito exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar item del carrito: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar el producto del carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar todo el carrito
     */
    public function limpiarCarrito(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'session_id' => 'nullable|string|max:255',
            ]);

            // Validar que se proporcione user_id o session_id
            if (empty($validated['user_id']) && empty($validated['session_id'])) {
                throw ValidationException::withMessages([
                    'identificacion' => ['Debe proporcionar user_id o session_id']
                ]);
            }

            $query = CarritoTemporal::query();
            
            if (!empty($validated['user_id'])) {
                $query->where('user_id', $validated['user_id']);
            }
            
            if (!empty($validated['session_id'])) {
                $query->where('session_id', $validated['session_id']);
            }

            $eliminados = $query->delete();

            return response()->json([
                'message' => 'Carrito limpiado exitosamente',
                'items_eliminados' => $eliminados
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al limpiar carrito: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al limpiar el carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular precio unitario del producto o variación
     */
    private function calcularPrecioUnitario(Producto $producto, ?VariacionProducto $variacion = null): float
    {
        if ($variacion) {
            // Si hay precio de oferta en la variación, usarlo
            if ($variacion->precio_oferta && $variacion->precio_oferta > 0) {
                return (float) $variacion->precio_oferta;
            }
            
            // Si no, usar el precio normal de la variación
            if ($variacion->precio && $variacion->precio > 0) {
                return (float) $variacion->precio;
            }
        }

        // Si no hay variación o no tiene precio, usar el precio del producto
        if ($producto->precio_oferta && $producto->precio_oferta > 0) {
            return (float) $producto->precio_oferta;
        }

        return (float) $producto->precio;
    }

    /**
     * Limpiar carritos expirados (método utilitario)
     */
    public function limpiarExpirados(): JsonResponse
    {
        try {
            $eliminados = CarritoTemporal::expirado()->delete();
            
            return response()->json([
                'message' => 'Carritos expirados limpiados exitosamente',
                'items_eliminados' => $eliminados
            ]);
        } catch (\Exception $e) {
            Log::error('Error al limpiar carritos expirados: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al limpiar carritos expirados',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 