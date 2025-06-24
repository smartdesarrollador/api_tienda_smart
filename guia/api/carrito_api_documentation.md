# API Documentation - Carrito de Compras

## 📋 **Resumen General**

Esta documentación describe la API RESTful del carrito de compras desarrollada en Laravel 10. La API está diseñada para sincronizar perfectamente con el frontend Angular 18 y proporciona todas las funcionalidades necesarias para una experiencia de e-commerce completa.

## 🛠️ **Endpoints Disponibles**

### **1. Obtener Carrito Actual**

```http
GET /api/carrito
```

**Descripción:** Obtiene el estado actual del carrito para la sesión.

**Respuesta:**

```json
{
    "success": true,
    "data": {
        "items": [
            {
                "id": "item_1_15_1234567890",
                "producto_id": 1,
                "variacion_id": 15,
                "nombre": "Smartphone Galaxy S23",
                "slug": "smartphone-galaxy-s23",
                "imagen": "/assets/productos/galaxy-s23.jpg",
                "precio": 2500.0,
                "precio_oferta": 2300.0,
                "cantidad": 2,
                "stock_disponible": 10,
                "sku": "GAL-S23-128-BLK",
                "peso": 0.5,
                "variacion": {
                    "color": {
                        "nombre": "Negro",
                        "hex": "#000000"
                    },
                    "talla": "128GB"
                },
                "subtotal": 4600.0,
                "agregado_en": "2024-01-15T10:30:00Z",
                "modificado_en": "2024-01-15T10:30:00Z"
            }
        ],
        "resumen": {
            "items_count": 1,
            "subtotal": 4600.0,
            "descuentos": 400.0,
            "descuentos_aplicados": [
                {
                    "tipo": "promocion",
                    "descripcion": "Oferta en Smartphone Galaxy S23",
                    "monto": 400.0,
                    "porcentaje": 8.0
                }
            ],
            "impuestos": 756.0,
            "costo_envio": 0.0,
            "envio_gratis": true,
            "total": 4956.0,
            "peso_total": 1.0
        },
        "cupon_aplicado": null,
        "envio": null,
        "cargando": false,
        "error": null,
        "guardado_en": "2024-01-15T10:30:00Z",
        "sincronizado": true
    },
    "message": "Carrito obtenido exitosamente"
}
```

---

### **2. Agregar Item al Carrito**

```http
POST /api/carrito/agregar
```

**Request Body:**

```json
{
    "producto_id": 1,
    "variacion_id": 15,
    "cantidad": 2
}
```

**Validaciones:**

-   `producto_id`: requerido, entero, existe en tabla productos
-   `variacion_id`: opcional, entero, existe en tabla variaciones
-   `cantidad`: requerido, entero, min:1, max:99

**Respuesta Exitosa:**

```json
{
    "success": true,
    "data": {
        // Estructura completa del carrito actualizado
    },
    "message": "Producto agregado al carrito exitosamente"
}
```

**Respuesta de Error:**

```json
{
    "success": false,
    "message": "Stock insuficiente",
    "errors": {
        "cantidad": "Solo hay 5 unidades disponibles"
    }
}
```

---

### **3. Actualizar Cantidad de Item**

```http
PUT /api/carrito/actualizar
```

**Request Body:**

```json
{
    "item_id": "item_1_15_1234567890",
    "cantidad": 3
}
```

**Validaciones:**

-   `item_id`: requerido, string, debe existir en el carrito
-   `cantidad`: requerido, entero, min:0, max:99

**Nota:** Si cantidad es 0, el item se elimina automáticamente.

---

### **4. Remover Item del Carrito**

```http
DELETE /api/carrito/remover/{itemId}
```

**Parámetros de URL:**

-   `itemId`: ID único del item en el carrito

**Respuesta:**

```json
{
    "success": true,
    "data": {
        // Carrito actualizado sin el item
    },
    "message": "Producto eliminado del carrito exitosamente"
}
```

---

### **5. Limpiar Carrito Completo**

```http
DELETE /api/carrito/limpiar
```

**Respuesta:**

```json
{
    "success": true,
    "data": {
        "items": [],
        "resumen": {
            "items_count": 0,
            "subtotal": 0.0,
            "descuentos": 0.0,
            "descuentos_aplicados": [],
            "impuestos": 0.0,
            "costo_envio": 0.0,
            "envio_gratis": false,
            "total": 0.0,
            "peso_total": 0.0
        },
        "cupon_aplicado": null,
        "envio": null,
        "cargando": false,
        "error": null,
        "guardado_en": "2024-01-15T10:35:00Z",
        "sincronizado": true
    },
    "message": "Carrito limpiado exitosamente"
}
```

---

### **6. Aplicar Cupón de Descuento**

```http
POST /api/carrito/aplicar-cupon
```

**Request Body:**

```json
{
    "codigo": "PRIMERACOMPRA"
}
```

**Validaciones:**

-   `codigo`: requerido, string, min:3, max:20, regex:[A-Za-z0-9\-_]+

**Respuesta Exitosa:**

```json
{
    "success": true,
    "data": {
        // Carrito con cupón aplicado
    },
    "message": "Cupón aplicado exitosamente",
    "descuento": 150.5
}
```

**Respuesta de Error:**

```json
{
    "success": false,
    "message": "Cupón no válido o expirado",
    "errors": {
        "codigo": "El cupón ingresado no es válido"
    }
}
```

---

### **7. Remover Cupón de Descuento**

```http
DELETE /api/carrito/remover-cupon/{codigo}
```

**Parámetros de URL:**

-   `codigo`: Código del cupón a eliminar

---

### **8. Calcular Opciones de Envío**

```http
POST /api/carrito/calcular-envio
```

**Request Body:**

```json
{
    "departamento": "Lima",
    "provincia": "Lima",
    "distrito": "Miraflores",
    "codigo_postal": "15074"
}
```

**Respuesta:**

```json
{
    "success": true,
    "data": {
        "opciones_disponibles": [
            {
                "id": "estandar",
                "nombre": "Envío Estándar",
                "descripcion": "Entrega en Lima Metropolitana en 1-2 días hábiles",
                "precio": 10.0,
                "tiempo_entrega_min": 24,
                "tiempo_entrega_max": 48,
                "tiempo_unidad": "horas",
                "empresa": "Courier Express",
                "incluye_seguro": false,
                "incluye_tracking": true,
                "logo_empresa": "/assets/empresas/courier-express.png",
                "gratis_desde": 150.0,
                "disponible": true,
                "transportista": "Courier Express",
                "icono": "📦",
                "es_gratis": true,
                "motivo_gratis": "Envío gratis por compras mayores a S/ 150"
            },
            {
                "id": "express",
                "nombre": "Envío Express",
                "descripcion": "Entrega el mismo día en Lima Metropolitana",
                "precio": 25.0,
                "tiempo_entrega_min": 4,
                "tiempo_entrega_max": 8,
                "tiempo_unidad": "horas",
                "empresa": "Express Lima",
                "incluye_seguro": true,
                "incluye_tracking": true,
                "logo_empresa": "/assets/empresas/express-lima.png",
                "gratis_desde": 300.0,
                "disponible": true,
                "transportista": "Express Lima",
                "icono": "⚡",
                "es_gratis": false,
                "falta_para_gratis": 145.5
            }
        ],
        "direccion": {
            "departamento": "Lima",
            "provincia": "Lima",
            "distrito": "Miraflores",
            "codigo_postal": "15074"
        }
    },
    "message": "Opciones de envío calculadas exitosamente"
}
```

---

### **9. Verificar Stock y Disponibilidad**

```http
POST /api/carrito/verificar-stock
```

**Respuesta:**

```json
{
    "success": true,
    "data": [
        // Items actualizados con stock correcto
    ],
    "message": "Verificación de stock completada",
    "items_sin_stock": [
        // Items que se eliminaron por falta de stock
    ],
    "items_actualizados": [
        // Items cuya cantidad se ajustó al stock disponible
    ]
}
```

---

### **10. Obtener Productos Relacionados**

```http
GET /api/carrito/productos-relacionados
```

**Respuesta:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "nombre": "Smartphone Premium",
            "slug": "smartphone-premium",
            "precio": 1200.0,
            "precio_oferta": 1100.0,
            "imagen": "/assets/productos/smartphone-premium.jpg",
            "stock": 15,
            "calificacion": 4.5,
            "categoria": "Electrónicos"
        }
    ],
    "message": "Productos relacionados obtenidos exitosamente"
}
```

---

### **11. Sincronizar Carrito**

```http
POST /api/carrito/sincronizar
```

**Request Body:**

```json
{
    "carrito": {
        // Datos del carrito desde el frontend
    }
}
```

**Uso:** Permite sincronizar el carrito almacenado en localStorage del frontend con el backend.

---

### **12. Obtener Configuración del Carrito**

```http
GET /api/carrito/configuracion
```

**Respuesta:**

```json
{
    "success": true,
    "data": {
        "maximo_items": 50,
        "maximo_cantidad_por_item": 99,
        "tiempo_sesion_minutos": 120,
        "auto_limpiar_items_sin_stock": true,
        "mostrar_productos_relacionados": true,
        "permitir_compra_sin_cuenta": true,
        "calcular_impuestos": true,
        "porcentaje_igv": 18.0
    },
    "message": "Configuración obtenida exitosamente"
}
```

---

## 🔧 **Gestión de Sesiones**

La API maneja automáticamente las sesiones del carrito:

-   **Usuarios autenticados:** `user_{user_id}`
-   **Usuarios guest:** `guest_{session_id}`

El carrito se almacena en **Redis Cache** con TTL de 2 horas (configurable).

---

## 📊 **Códigos de Estado HTTP**

| Código | Descripción                             |
| ------ | --------------------------------------- |
| 200    | Operación exitosa                       |
| 201    | Recurso creado exitosamente             |
| 400    | Error de validación o lógica de negocio |
| 404    | Recurso no encontrado                   |
| 422    | Error de validación de formulario       |
| 500    | Error interno del servidor              |

---

## ⚡ **Características Técnicas**

### **Arquitectura**

-   **Controlador:** `CarritoController` - Maneja las peticiones HTTP
-   **Servicio Principal:** `CarritoService` - Lógica de negocio del carrito
-   **Servicio de Envío:** `EnvioService` - Cálculos de shipping
-   **Form Requests:** Validación robusta de entrada
-   **Cache:** Redis para persistencia temporal

### **Validaciones Implementadas**

-   Verificación de stock en tiempo real
-   Validación de productos activos
-   Límites de cantidad por item
-   Verificación de cupones válidos y vigentes
-   Cálculo automático de descuentos e impuestos

### **Optimizaciones**

-   Cache con TTL configurable
-   Cálculos optimizados de resumen
-   Manejo eficiente de sesiones
-   Logging de errores para debugging

### **Seguridad**

-   Validación estricta de inputs
-   Sanitización de datos
-   Manejo seguro de sesiones
-   Logging de operaciones sensibles

---

## 🧪 **Testing y Debugging**

### **Variables de Entorno Requeridas**

```env
# Carrito Configuration
CARRITO_MAXIMO_ITEMS=50
CARRITO_MAXIMO_CANTIDAD_POR_ITEM=99
CARRITO_TIEMPO_SESION_MINUTOS=120
CARRITO_ENVIO_GRATIS_MONTO=150.0
CARRITO_PORCENTAJE_IGV=18.0
CARRITO_CACHE_TTL=7200

# Cache Configuration
CARRITO_CACHE_DRIVER=redis
```

### **Comandos Útiles**

```bash
# Limpiar cache del carrito
php artisan cache:forget carrito:*

# Ver logs de errores
tail -f storage/logs/laravel.log | grep carrito

# Verificar configuración
php artisan config:show carrito
```

---

## 🚀 **Integración con Frontend Angular**

La API está diseñada para funcionar perfectamente con las interfaces TypeScript definidas en Angular:

-   ✅ Estructura de respuestas compatible con `carrito.interface.ts`
-   ✅ Endpoints que coinciden con métodos de `carrito.service.ts`
-   ✅ Manejo de errores consistente
-   ✅ Formato de datos optimizado para Angular Signals

### **Ejemplo de Integración**

```typescript
// En Angular
this.carritoService
    .agregarItem({
        producto_id: 1,
        variacion_id: 15,
        cantidad: 2,
    })
    .subscribe({
        next: (response) => {
            if (response.success) {
                // Actualizar estado local
                this.actualizarCarritoLocal(response.data);
            }
        },
        error: (error) => {
            // Manejar errores
            console.error("Error:", error);
        },
    });
```

---

## 📈 **Roadmap de Mejoras**

### **Próximas Implementaciones**

-   [ ] Carritos guardados para usuarios registrados
-   [ ] Recuperación de carritos abandonados
-   [ ] Analytics de comportamiento de carrito
-   [ ] API de recomendaciones inteligentes
-   [ ] Integración con sistemas de pago
-   [ ] Notificaciones push para promociones

### **Optimizaciones Futuras**

-   [ ] Cache distribuido para alta concurrencia
-   [ ] Rate limiting por usuario
-   [ ] Compresión de respuestas JSON
-   [ ] Métricas de performance
-   [ ] Testing automatizado completo

---

La API del carrito está completamente implementada y lista para producción, proporcionando una base sólida y escalable para el e-commerce de la tienda virtual. 🎉
