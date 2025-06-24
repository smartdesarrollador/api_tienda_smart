<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración del Carrito de Compras
    |--------------------------------------------------------------------------
    |
    | Configuraciones generales para el funcionamiento del carrito de compras.
    | Estos valores pueden ser modificados según las necesidades del negocio.
    |
    */

    'maximo_items' => env('CARRITO_MAXIMO_ITEMS', 50),

    'maximo_cantidad_por_item' => env('CARRITO_MAXIMO_CANTIDAD_POR_ITEM', 99),

    'tiempo_sesion_minutos' => env('CARRITO_TIEMPO_SESION_MINUTOS', 120),

    'auto_limpiar_items_sin_stock' => env('CARRITO_AUTO_LIMPIAR_SIN_STOCK', true),

    'mostrar_productos_relacionados' => env('CARRITO_MOSTRAR_PRODUCTOS_RELACIONADOS', true),

    'permitir_compra_sin_cuenta' => env('CARRITO_PERMITIR_COMPRA_SIN_CUENTA', true),

    'calcular_impuestos' => env('CARRITO_CALCULAR_IMPUESTOS', true),

    'porcentaje_igv' => env('CARRITO_PORCENTAJE_IGV', 18.0),

    /*
    |--------------------------------------------------------------------------
    | Configuración de Envío
    |--------------------------------------------------------------------------
    */

    'envio_gratis_monto' => env('CARRITO_ENVIO_GRATIS_MONTO', 150.0),

    'costo_envio_lima' => env('CARRITO_COSTO_ENVIO_LIMA', 10.0),

    'costo_envio_nacional' => env('CARRITO_COSTO_ENVIO_NACIONAL', 20.0),

    /*
    |--------------------------------------------------------------------------
    | Configuración de Descuentos
    |--------------------------------------------------------------------------
    */

    'max_cupones_simultaneos' => env('CARRITO_MAX_CUPONES_SIMULTANEOS', 1),

    'permitir_acumular_descuentos' => env('CARRITO_PERMITIR_ACUMULAR_DESCUENTOS', false),

    /*
    |--------------------------------------------------------------------------
    | Configuración de Cache
    |--------------------------------------------------------------------------
    */

    'cache_ttl_segundos' => env('CARRITO_CACHE_TTL', 7200), // 2 horas

    'cache_driver' => env('CARRITO_CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Configuración de Notificaciones
    |--------------------------------------------------------------------------
    */

    'notificar_stock_bajo' => env('CARRITO_NOTIFICAR_STOCK_BAJO', true),

    'notificar_precio_cambio' => env('CARRITO_NOTIFICAR_PRECIO_CAMBIO', true),

    'notificar_item_removido' => env('CARRITO_NOTIFICAR_ITEM_REMOVIDO', true),
]; 