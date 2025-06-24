<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Carrito\AgregarItemRequest;
use App\Http\Requests\Carrito\ActualizarCantidadRequest;
use App\Http\Requests\Carrito\AplicarCuponRequest;
use App\Http\Requests\Carrito\CalcularEnvioRequest;
use App\Models\Producto;
use App\Models\VariacionProducto;
use App\Models\Cupon;
use App\Services\CarritoService;
use App\Services\EnvioService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CarritoController extends Controller
{
    public function __construct(
        private readonly CarritoService $carritoService,
        private readonly EnvioService $envioService
    ) {}

    /**
     * Obtener el carrito actual
     */
    public function index(): JsonResponse
    {
        try {
            $sessionId = $this->obtenerSessionId();
            $carrito = $this->carritoService->obtenerCarrito($sessionId);

            return response()->json([
                'success' => true,
                'data' => $carrito,
                'message' => 'Carrito obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener carrito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el carrito',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Agregar item al carrito
     */
    public function agregar(AgregarItemRequest $request): JsonResponse
    {
        try {
            $sessionId = $this->obtenerSessionId();
            $data = $request->validated();

            // Verificar que el producto existe y está activo
            $producto = Producto::with(['variaciones', 'categoria'])
                ->where('id', $data['producto_id'])
                ->where('activo', true)
                ->first();

            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado o no disponible',
                    'errors' => ['producto_id' => 'El producto no está disponible']
                ], 404);
            }

            // Verificar variación si se especifica
            $variacion = null;
            if (isset($data['variacion_id'])) {
                $variacion = $producto->variaciones()
                    ->where('id', $data['variacion_id'])
                    ->where('activo', true)
                    ->first();

                if (!$variacion) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Variación no encontrada',
                        'errors' => ['variacion_id' => 'La variación seleccionada no está disponible']
                    ], 404);
                }
            }

            // Verificar stock disponible
            $stockDisponible = $variacion ? $variacion->stock : $producto->stock;
            if ($stockDisponible < $data['cantidad']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock insuficiente',
                    'errors' => ['cantidad' => "Solo hay {$stockDisponible} unidades disponibles"]
                ], 400);
            }

            // Agregar al carrito
            $carrito = $this->carritoService->agregarItem(
                $sessionId,
                $producto,
                $variacion,
                $data['cantidad']
            );

            return response()->json([
                'success' => true,
                'data' => $carrito,
                'message' => 'Producto agregado al carrito exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al agregar item al carrito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar producto al carrito',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Actualizar cantidad de un item
     */
    public function actualizar(ActualizarCantidadRequest $request): JsonResponse
    {
        try {
            $sessionId = $this->obtenerSessionId();
            $data = $request->validated();

            $carrito = $this->carritoService->actualizarCantidad(
                $sessionId,
                $data['item_id'],
                $data['cantidad']
            );

            if (!$carrito) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item no encontrado en el carrito',
                    'errors' => ['item_id' => 'El item no existe en el carrito']
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $carrito,
                'message' => 'Cantidad actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar cantidad: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cantidad',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Remover item del carrito
     */
    public function remover(string $itemId): JsonResponse
    {
        try {
            $sessionId = $this->obtenerSessionId();

            $carrito = $this->carritoService->removerItem($sessionId, $itemId);

            if (!$carrito) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item no encontrado en el carrito',
                    'errors' => ['item_id' => 'El item no existe en el carrito']
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $carrito,
                'message' => 'Producto eliminado del carrito exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al remover item del carrito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar producto del carrito',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Limpiar carrito completo
     */
    public function limpiar(): JsonResponse
    {
        try {
            $sessionId = $this->obtenerSessionId();

            $carrito = $this->carritoService->limpiarCarrito($sessionId);

            return response()->json([
                'success' => true,
                'data' => $carrito,
                'message' => 'Carrito limpiado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al limpiar carrito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar carrito',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Aplicar cupón de descuento
     */
    public function aplicarCupon(AplicarCuponRequest $request): JsonResponse
    {
        try {
            $sessionId = $this->obtenerSessionId();
            $data = $request->validated();

            $resultado = $this->carritoService->aplicarCupon($sessionId, $data['codigo']);

            if (!$resultado['valido']) {
                return response()->json([
                    'success' => false,
                    'message' => $resultado['mensaje'],
                    'errors' => ['codigo' => $resultado['mensaje']]
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $resultado['carrito'],
                'message' => 'Cupón aplicado exitosamente',
                'descuento' => $resultado['descuento_calculado']
            ]);

        } catch (\Exception $e) {
            Log::error('Error al aplicar cupón: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al aplicar cupón',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Remover cupón de descuento
     */
    public function removerCupon(string $codigo): JsonResponse
    {
        try {
            $sessionId = $this->obtenerSessionId();

            $carrito = $this->carritoService->removerCupon($sessionId, $codigo);

            return response()->json([
                'success' => true,
                'data' => $carrito,
                'message' => 'Cupón eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al remover cupón: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar cupón',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Calcular opciones de envío
     */
    public function calcularEnvio(CalcularEnvioRequest $request): JsonResponse
    {
        try {
            $sessionId = $this->obtenerSessionId();
            $data = $request->validated();

            $carrito = $this->carritoService->obtenerCarrito($sessionId);
            
            if (empty($carrito['items'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'El carrito está vacío',
                    'errors' => ['carrito' => 'Agrega productos para calcular el envío']
                ], 400);
            }

            $opcionesEnvio = $this->envioService->calcularOpciones(
                $data['departamento'],
                $data['provincia'],
                $data['distrito'],
                $carrito['resumen']['peso_total'],
                $carrito['resumen']['subtotal']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'opciones_disponibles' => $opcionesEnvio,
                    'direccion' => [
                        'departamento' => $data['departamento'],
                        'provincia' => $data['provincia'],
                        'distrito' => $data['distrito'],
                        'codigo_postal' => $data['codigo_postal'] ?? null
                    ]
                ],
                'message' => 'Opciones de envío calculadas exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al calcular envío: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular opciones de envío',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Verificar disponibilidad de items en el carrito
     */
    public function verificarStock(): JsonResponse
    {
        try {
            $sessionId = $this->obtenerSessionId();

            $resultado = $this->carritoService->verificarDisponibilidad($sessionId);

            return response()->json([
                'success' => true,
                'data' => $resultado['items_actualizados'],
                'message' => 'Verificación de stock completada',
                'items_sin_stock' => $resultado['items_sin_stock'],
                'items_actualizados' => $resultado['items_con_cambios']
            ]);

        } catch (\Exception $e) {
            Log::error('Error al verificar stock: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar disponibilidad',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Obtener productos relacionados para carrito vacío
     */
    public function productosRelacionados(): JsonResponse
    {
        try {
            $productos = Producto::where('activo', true)
                ->where('stock', '>', 0)
                ->where('destacado', true)
                ->orderBy('id', 'desc')
                ->limit(8)
                ->get()
                ->map(function ($producto) {
                    return [
                        'id' => $producto->id,
                        'nombre' => $producto->nombre,
                        'slug' => $producto->slug,
                        'precio' => $producto->precio,
                        'precio_oferta' => $producto->precio_oferta,
                        'imagen' => $producto->imagen_principal ? url($producto->imagen_principal) : url('/assets/productos/default.jpg'),
                        'stock' => $producto->stock,
                        'calificacion' => 0,
                        'categoria' => 'Producto destacado'
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $productos,
                'message' => 'Productos relacionados obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener productos relacionados: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener productos relacionados',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Sincronizar carrito del frontend con backend
     */
    public function sincronizar(Request $request): JsonResponse
    {
        try {
            $sessionId = $this->obtenerSessionId();
            $carritoFrontend = $request->input('carrito', []);

            $carrito = $this->carritoService->sincronizarCarrito($sessionId, $carritoFrontend);

            return response()->json([
                'success' => true,
                'data' => $carrito,
                'message' => 'Carrito sincronizado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al sincronizar carrito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar carrito',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Obtener configuración del carrito
     */
    public function configuracion(): JsonResponse
    {
        try {
            $configuracion = [
                'maximo_items' => (int) config('carrito.maximo_items', 50),
                'maximo_cantidad_por_item' => (int) config('carrito.maximo_cantidad_por_item', 99),
                'tiempo_sesion_minutos' => (int) config('carrito.tiempo_sesion_minutos', 120),
                'auto_limpiar_items_sin_stock' => (bool) config('carrito.auto_limpiar_items_sin_stock', true),
                'mostrar_productos_relacionados' => (bool) config('carrito.mostrar_productos_relacionados', true),
                'permitir_compra_sin_cuenta' => (bool) config('carrito.permitir_compra_sin_cuenta', true),
                'calcular_impuestos' => (bool) config('carrito.calcular_impuestos', true),
                'porcentaje_igv' => (float) config('carrito.porcentaje_igv', 18.0)
            ];

            return response()->json([
                'success' => true,
                'data' => $configuracion,
                'message' => 'Configuración obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener configuración: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener configuración',
                'errors' => ['general' => 'Error interno del servidor']
            ], 500);
        }
    }

    /**
     * Obtener o generar session ID para el carrito
     */
    private function obtenerSessionId(): string
    {
        // Si el usuario está autenticado, usar su ID
        if (Auth::check()) {
            return 'user_' . Auth::id();
        }

        // Para usuarios guest, usar un ID simple basado en IP y user agent
        $userAgent = request()->header('User-Agent', 'unknown');
        $ipAddress = request()->ip();
        
        // Generar un hash simple pero consistente para la sesión
        $sessionHash = md5($ipAddress . $userAgent);
        
        return 'guest_' . $sessionHash;
    }
} 