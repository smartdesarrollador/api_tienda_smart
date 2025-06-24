<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Producto;
use App\Models\VariacionProducto;
use App\Models\Cupon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CarritoService
{
    private const CACHE_PREFIX = 'carrito:';
    private const CACHE_TTL = 7200; // 2 horas

    /**
     * Obtener carrito por session ID
     */
    public function obtenerCarrito(string $sessionId): array
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        
        $carrito = Cache::get($cacheKey, [
            'items' => [],
            'resumen' => $this->calcularResumen([]),
            'cupon_aplicado' => null,
            'envio' => null,
            'cargando' => false,
            'error' => null,
            'guardado_en' => now(),
            'sincronizado' => true
        ]);

        // Recalcular resumen si hay items
        if (!empty($carrito['items'])) {
            $carrito['resumen'] = $this->calcularResumen($carrito['items']);
        }

        return $carrito;
    }

    /**
     * Agregar item al carrito
     */
    public function agregarItem(
        string $sessionId, 
        Producto $producto, 
        ?VariacionProducto $variacion, 
        int $cantidad
    ): array {
        $carrito = $this->obtenerCarrito($sessionId);

        // Crear ID único para el item
        $itemId = $this->generarItemId($producto->id, $variacion?->id);

        // Verificar si el item ya existe
        $itemExistente = collect($carrito['items'])->firstWhere('id', $itemId);

        if ($itemExistente) {
            // Actualizar cantidad si ya existe
            $nuevaCantidad = $itemExistente['cantidad'] + $cantidad;
            return $this->actualizarCantidad($sessionId, $itemId, $nuevaCantidad);
        }

        // Crear nuevo item
        $nuevoItem = $this->crearItemCarrito($producto, $variacion, $cantidad, $itemId);
        $carrito['items'][] = $nuevoItem;

        // Recalcular resumen
        $carrito['resumen'] = $this->calcularResumen($carrito['items']);
        $carrito['guardado_en'] = now();
        $carrito['sincronizado'] = false;

        // Guardar en caché
        $this->guardarCarrito($sessionId, $carrito);

        return $carrito;
    }

    /**
     * Actualizar cantidad de un item
     */
    public function actualizarCantidad(string $sessionId, string $itemId, int $cantidad): ?array
    {
        $carrito = $this->obtenerCarrito($sessionId);

        $itemIndex = collect($carrito['items'])->search(function ($item) use ($itemId) {
            return $item['id'] === $itemId;
        });

        if ($itemIndex === false) {
            return null;
        }

        if ($cantidad <= 0) {
            // Remover item si cantidad es 0 o menor
            return $this->removerItem($sessionId, $itemId);
        }

        // Actualizar cantidad
        $carrito['items'][$itemIndex]['cantidad'] = $cantidad;
        $carrito['items'][$itemIndex]['subtotal'] = $this->calcularSubtotalItem($carrito['items'][$itemIndex]);
        $carrito['items'][$itemIndex]['modificado_en'] = now();

        // Recalcular resumen
        $carrito['resumen'] = $this->calcularResumen($carrito['items']);
        $carrito['guardado_en'] = now();
        $carrito['sincronizado'] = false;

        $this->guardarCarrito($sessionId, $carrito);

        return $carrito;
    }

    /**
     * Remover item del carrito
     */
    public function removerItem(string $sessionId, string $itemId): ?array
    {
        $carrito = $this->obtenerCarrito($sessionId);

        $itemIndex = collect($carrito['items'])->search(function ($item) use ($itemId) {
            return $item['id'] === $itemId;
        });

        if ($itemIndex === false) {
            return null;
        }

        // Remover item
        array_splice($carrito['items'], $itemIndex, 1);

        // Recalcular resumen
        $carrito['resumen'] = $this->calcularResumen($carrito['items']);
        $carrito['guardado_en'] = now();
        $carrito['sincronizado'] = false;

        $this->guardarCarrito($sessionId, $carrito);

        return $carrito;
    }

    /**
     * Limpiar carrito completo
     */
    public function limpiarCarrito(string $sessionId): array
    {
        $carrito = [
            'items' => [],
            'resumen' => $this->calcularResumen([]),
            'cupon_aplicado' => null,
            'envio' => null,
            'cargando' => false,
            'error' => null,
            'guardado_en' => now(),
            'sincronizado' => true
        ];

        $this->guardarCarrito($sessionId, $carrito);

        return $carrito;
    }

    /**
     * Aplicar cupón de descuento
     */
    public function aplicarCupon(string $sessionId, string $codigo): array
    {
        $carrito = $this->obtenerCarrito($sessionId);

        // Buscar cupón
        $cupon = Cupon::where('codigo', $codigo)
            ->where('activo', true)
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now())
            ->first();

        if (!$cupon) {
            return [
                'valido' => false,
                'mensaje' => 'Cupón no válido o expirado',
                'carrito' => $carrito
            ];
        }

        // Verificar si ya hay un cupón aplicado
        if ($carrito['cupon_aplicado']) {
            return [
                'valido' => false,
                'mensaje' => 'Ya tienes un cupón aplicado. Elimínalo primero.',
                'carrito' => $carrito
            ];
        }

        // Verificar monto mínimo
        if ($cupon->monto_minimo && $carrito['resumen']['subtotal'] < $cupon->monto_minimo) {
            return [
                'valido' => false,
                'mensaje' => "Monto mínimo requerido: S/ {$cupon->monto_minimo}",
                'carrito' => $carrito
            ];
        }

        // Aplicar cupón
        $carrito['cupon_aplicado'] = [
            'id' => $cupon->id,
            'codigo' => $cupon->codigo,
            'descripcion' => $cupon->descripcion,
            'tipo' => $cupon->tipo,
            'valor' => $cupon->valor,
            'fecha_inicio' => $cupon->fecha_inicio,
            'fecha_fin' => $cupon->fecha_fin,
            'activo' => true
        ];

        // Recalcular resumen con descuento
        $carrito['resumen'] = $this->calcularResumen($carrito['items'], $carrito['cupon_aplicado']);
        $carrito['guardado_en'] = now();

        $this->guardarCarrito($sessionId, $carrito);

        return [
            'valido' => true,
            'mensaje' => 'Cupón aplicado exitosamente',
            'descuento_calculado' => $this->calcularDescuentoCupon($carrito['resumen']['subtotal'], $cupon),
            'carrito' => $carrito
        ];
    }

    /**
     * Remover cupón aplicado
     */
    public function removerCupon(string $sessionId, string $codigo): array
    {
        $carrito = $this->obtenerCarrito($sessionId);

        $carrito['cupon_aplicado'] = null;
        $carrito['resumen'] = $this->calcularResumen($carrito['items']);
        $carrito['guardado_en'] = now();

        $this->guardarCarrito($sessionId, $carrito);

        return $carrito;
    }

    /**
     * Verificar disponibilidad de items
     */
    public function verificarDisponibilidad(string $sessionId): array
    {
        $carrito = $this->obtenerCarrito($sessionId);
        $itemsSinStock = [];
        $itemsConCambios = [];

        foreach ($carrito['items'] as $index => $item) {
            $producto = Producto::find($item['producto_id']);
            
            if (!$producto || !$producto->activo) {
                $itemsSinStock[] = $item;
                unset($carrito['items'][$index]);
                continue;
            }

            // Verificar variación si existe
            $stockDisponible = $producto->stock;
            if ($item['variacion_id']) {
                $variacion = $producto->variaciones()->find($item['variacion_id']);
                if (!$variacion || !$variacion->activo) {
                    $itemsSinStock[] = $item;
                    unset($carrito['items'][$index]);
                    continue;
                }
                $stockDisponible = $variacion->stock;
            }

            // Verificar stock
            if ($stockDisponible < $item['cantidad']) {
                if ($stockDisponible > 0) {
                    // Ajustar cantidad al stock disponible
                    $carrito['items'][$index]['cantidad'] = $stockDisponible;
                    $carrito['items'][$index]['subtotal'] = $this->calcularSubtotalItem($carrito['items'][$index]);
                    $itemsConCambios[] = $carrito['items'][$index];
                } else {
                    // Sin stock disponible
                    $itemsSinStock[] = $item;
                    unset($carrito['items'][$index]);
                }
            }
        }

        // Reindexar array
        $carrito['items'] = array_values($carrito['items']);

        // Recalcular resumen
        $carrito['resumen'] = $this->calcularResumen($carrito['items'], $carrito['cupon_aplicado']);
        $carrito['guardado_en'] = now();

        $this->guardarCarrito($sessionId, $carrito);

        return [
            'items_actualizados' => $carrito['items'],
            'items_sin_stock' => $itemsSinStock,
            'items_con_cambios' => $itemsConCambios
        ];
    }

    /**
     * Sincronizar carrito del frontend
     */
    public function sincronizarCarrito(string $sessionId, array $carritoFrontend): array
    {
        // En un caso real, aquí se sincronizaría el carrito del frontend con el backend
        // Por ahora, retornamos el carrito actual del backend
        return $this->obtenerCarrito($sessionId);
    }

    /**
     * Crear item del carrito
     */
    private function crearItemCarrito(Producto $producto, ?VariacionProducto $variacion, int $cantidad, string $itemId): array
    {
        $precio = $variacion ? $variacion->precio : $producto->precio;
        $precioOferta = $variacion ? $variacion->precio_oferta : $producto->precio_oferta;
        $peso = $variacion ? $variacion->peso : $producto->peso;

        $item = [
            'id' => $itemId,
            'producto_id' => $producto->id,
            'variacion_id' => $variacion?->id,
            'nombre' => $producto->nombre,
            'slug' => $producto->slug,
            'imagen' => $producto->imagen_principal ? url($producto->imagen_principal) : url('/assets/productos/default.jpg'),
            'precio' => $precio,
            'precio_oferta' => $precioOferta,
            'cantidad' => $cantidad,
            'stock_disponible' => $variacion ? $variacion->stock : $producto->stock,
            'sku' => $variacion ? $variacion->sku : $producto->sku,
            'peso' => $peso ?? 0.5,
            'agregado_en' => now(),
            'modificado_en' => now()
        ];

        // Agregar información de variación si existe
        if ($variacion) {
            $item['variacion'] = [
                'color' => $variacion->color ? [
                    'nombre' => $variacion->color->nombre,
                    'hex' => $variacion->color->hex
                ] : null,
                'talla' => $variacion->talla
            ];
        }

        $item['subtotal'] = $this->calcularSubtotalItem($item);

        return $item;
    }

    /**
     * Calcular subtotal de un item
     */
    private function calcularSubtotalItem(array $item): float
    {
        $precio = $item['precio_oferta'] ?? $item['precio'];
        return $precio * $item['cantidad'];
    }

    /**
     * Calcular resumen del carrito
     */
    private function calcularResumen(array $items, ?array $cuponAplicado = null): array
    {
        $itemsCount = count($items);
        $subtotal = array_sum(array_column($items, 'subtotal'));
        $pesoTotal = array_sum(array_map(fn($item) => $item['peso'] * $item['cantidad'], $items));

        // Calcular descuentos
        $descuentos = 0;
        $descuentosAplicados = [];

        // Descuentos por ofertas
        foreach ($items as $item) {
            if (isset($item['precio_oferta']) && $item['precio_oferta'] < $item['precio']) {
                $descuentoItem = ($item['precio'] - $item['precio_oferta']) * $item['cantidad'];
                $descuentos += $descuentoItem;
                $descuentosAplicados[] = [
                    'tipo' => 'promocion',
                    'descripcion' => "Oferta en {$item['nombre']}",
                    'monto' => $descuentoItem,
                    'porcentaje' => (($item['precio'] - $item['precio_oferta']) / $item['precio']) * 100
                ];
            }
        }

        // Descuento por cupón
        if ($cuponAplicado) {
            $descuentoCupon = $this->calcularDescuentoCupon($subtotal, (object) $cuponAplicado);
            $descuentos += $descuentoCupon;
            $descuentosAplicados[] = [
                'tipo' => 'cupon',
                'codigo' => $cuponAplicado['codigo'],
                'descripcion' => $cuponAplicado['descripcion'],
                'monto' => $descuentoCupon,
                'porcentaje' => $cuponAplicado['tipo'] === 'porcentaje' ? $cuponAplicado['valor'] : null
            ];
        }

        // Calcular IGV (18%)
        $baseImponible = $subtotal - $descuentos;
        $impuestos = $baseImponible * 0.18;

        // Costo de envío (se calculará en otro servicio)
        $costoEnvio = 0;
        $envioGratis = $subtotal >= 150; // Envío gratis desde S/ 150

        $total = $subtotal - $descuentos + $impuestos + $costoEnvio;

        return [
            'items_count' => $itemsCount,
            'subtotal' => round($subtotal, 2),
            'descuentos' => round($descuentos, 2),
            'descuentos_aplicados' => $descuentosAplicados,
            'impuestos' => round($impuestos, 2),
            'costo_envio' => $costoEnvio,
            'envio_gratis' => $envioGratis,
            'total' => round($total, 2),
            'peso_total' => round($pesoTotal, 2)
        ];
    }

    /**
     * Calcular descuento de cupón
     */
    private function calcularDescuentoCupon(float $subtotal, object $cupon): float
    {
        switch ($cupon->tipo) {
            case 'porcentaje':
                $descuento = ($subtotal * $cupon->valor) / 100;
                break;
            case 'monto_fijo':
                $descuento = $cupon->valor;
                break;
            case 'envio_gratis':
                $descuento = 0; // Se aplicará en el cálculo de envío
                break;
            default:
                $descuento = 0;
        }

        // Aplicar monto máximo si existe
        if (isset($cupon->monto_maximo) && $descuento > $cupon->monto_maximo) {
            $descuento = $cupon->monto_maximo;
        }

        return round($descuento, 2);
    }

    /**
     * Generar ID único para item
     */
    private function generarItemId(int $productoId, ?int $variacionId): string
    {
        return 'item_' . $productoId . ($variacionId ? '_' . $variacionId : '') . '_' . time();
    }

    /**
     * Guardar carrito en caché
     */
    private function guardarCarrito(string $sessionId, array $carrito): void
    {
        $cacheKey = self::CACHE_PREFIX . $sessionId;
        Cache::put($cacheKey, $carrito, self::CACHE_TTL);
    }
} 