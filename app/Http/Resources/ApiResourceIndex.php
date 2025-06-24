<?php

declare(strict_types=1);

namespace App\Http\Resources;

/**
 * ÍNDICE DE API RESOURCES - TIENDA VIRTUAL
 * 
 * Este archivo sirve como documentación y referencia rápida de todos
 * los API Resources disponibles en el sistema de tienda virtual.
 * 
 * ESTRUCTURA DE RESOURCES:
 * 
 * 📋 RESOURCES PRINCIPALES:
 * - UserResource: Usuario completo con relaciones y crédito
 * - ProductoResource: Producto completo con variaciones e imágenes
 * - CategoriaResource: Categoría con jerarquía y subcategorías
 * - PedidoResource: Pedido completo con detalles y pagos
 * - VariacionProductoResource: Variaciones de producto con atributos
 * 
 * 🎯 RESOURCES SIMPLIFICADOS (Para performance):
 * - ProductoSimpleResource: Versión optimizada para listados
 * - UserSimpleResource: Usuario básico para relaciones
 * - ProductoCollection: Collection con metadatos y filtros
 * 
 * 💳 RESOURCES DE COMERCIO:
 * - PagoResource: Información detallada de pagos
 * - CuotaCreditoResource: Cuotas con cálculos de mora
 * - CuponResource: Cupones con validación de vigencia
 * - DetallePedidoResource: Líneas de pedido con cálculos
 * 
 * 🛠️ RESOURCES DE CONFIGURACIÓN:
 * - AtributoResource: Atributos de productos
 * - ValorAtributoResource: Valores de atributos
 * - ImagenProductoResource: Imágenes con thumbnails optimizados
 * - DireccionResource: Direcciones formateadas
 * 
 * 📱 RESOURCES DE INTERACCIÓN:
 * - ComentarioResource: Comentarios con rating y tiempo
 * - FavoritoResource: Favoritos con info del producto
 * - NotificacionResource: Notificaciones tipificadas
 * 
 * USO RECOMENDADO:
 * 
 * Para Listados:
 * - ProductoSimpleResource en lugar de ProductoResource
 * - ProductoCollection para metadatos adicionales
 * - UserSimpleResource en relaciones
 * 
 * Para Detalles:
 * - ProductoResource con todas las relaciones
 * - UserResource para perfiles completos
 * - PedidoResource para vista completa de pedidos
 * 
 * Para APIs Mobile:
 * - Preferir resources simplificados
 * - Usar lazy loading con whenLoaded()
 * - Implementar paginación con Collections
 * 
 * PATRONES IMPLEMENTADOS:
 * - Conditional Loading: whenLoaded(), when()
 * - Type Casting: (float), (bool)
 * - Date Formatting: Y-m-d H:i:s, diffForHumans()
 * - Business Logic: métodos privados para cálculos
 * - Nested Resources: relaciones anidadas optimizadas
 * 
 * OPTIMIZACIONES:
 * - Lazy loading de relaciones
 * - Thumbnail generation para imágenes
 * - Cálculos de descuentos en el resource
 * - Estados de stock dinámicos
 * - Formateo de fechas legible
 */

class ApiResourceIndex
{
    /**
     * Lista todos los resources disponibles agrupados por categoría
     */
    public static function getAllResources(): array
    {
        return [
            'principales' => [
                'UserResource',
                'ProductoResource', 
                'CategoriaResource',
                'PedidoResource',
                'VariacionProductoResource',
            ],
            'simplificados' => [
                'ProductoSimpleResource',
                'UserSimpleResource',
                'ProductoCollection',
            ],
            'comercio' => [
                'PagoResource',
                'CuotaCreditoResource',
                'CuponResource',
                'DetallePedidoResource',
            ],
            'configuracion' => [
                'AtributoResource',
                'ValorAtributoResource',
                'ImagenProductoResource',
                'DireccionResource',
            ],
            'interaccion' => [
                'ComentarioResource',
                'FavoritoResource',
                'NotificacionResource',
            ]
        ];
    }
    
    /**
     * Retorna recomendaciones de uso por contexto
     */
    public static function getUsageRecommendations(): array
    {
        return [
            'listado_productos' => 'ProductoSimpleResource + ProductoCollection',
            'detalle_producto' => 'ProductoResource con relaciones',
            'carrito_compras' => 'ProductoSimpleResource',
            'historial_pedidos' => 'PedidoResource',
            'perfil_usuario' => 'UserResource',
            'favoritos' => 'FavoritoResource',
            'notificaciones' => 'NotificacionResource',
            'api_mobile' => 'Resources simplificados',
        ];
    }
} 