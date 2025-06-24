# Seeders para Tienda Virtual - Sistema Completo

Este proyecto incluye un conjunto completo de seeders para poblar la base de datos con datos realistas de una tienda virtual de productos tecnológicos con sistema de crédito.

## Seeders Creados

### Seeders Básicos (sin dependencias)

-   **UserSeeder**: Usuarios con diferentes roles (administrador, vendedor, soporte, repartidor, clientes)
-   **CategoriaSeeder**: Categorías y subcategorías de productos tecnológicos
-   **AtributoSeeder**: Atributos de productos (Color, Tamaño, RAM, Almacenamiento, etc.)
-   **ValorAtributoSeeder**: Valores específicos para cada atributo
-   **CuponSeeder**: Cupones de descuento con diferentes tipos y vigencias
-   **FiltroAvanzadoSeeder**: Filtros avanzados para búsqueda de productos

### Seeders con Dependencias

-   **ProductoSeeder**: Productos tecnológicos realistas (iPhones, Samsung, Laptops, etc.)
-   **VariacionProductoSeeder**: Variaciones de productos por color y almacenamiento
-   **ImagenProductoSeeder**: Imágenes adicionales para productos (galerías, detalles, zoom)

### Seeders de Pedidos y Transacciones

-   **PedidoSeeder**: Pedidos de clientes con diferentes tipos de pago y estados
-   **DetallePedidoSeeder**: Detalles de pedidos con productos y variaciones
-   **PagoSeeder**: Pagos realizados (contado, transferencias, cuotas de crédito)
-   **CuotaCreditoSeeder**: Sistema completo de cuotas de crédito con estados y moras

### Seeders de Interacción de Usuarios

-   **DireccionSeeder**: Direcciones de entrega para clientes
-   **NotificacionSeeder**: Notificaciones del sistema para usuarios
-   **FavoritoSeeder**: Productos favoritos de los clientes
-   **ComentarioSeeder**: Reseñas y comentarios de productos con calificaciones

## Cómo Ejecutar los Seeders

### 1. Ejecutar todos los seeders principales

```bash
php artisan db:seed
```

### 2. Ejecutar seeders específicos

```bash
# Solo usuarios
php artisan db:seed --class=UserSeeder

# Solo productos
php artisan db:seed --class=ProductoSeeder

# Solo pedidos
php artisan db:seed --class=PedidoSeeder
```

### 3. Restablecer y ejecutar todos los seeders

```bash
php artisan migrate:fresh --seed
```

## Datos de Ejemplo Incluidos

### Usuarios Creados

-   **Administrador**: admin@tiendavirtual.com / admin123
-   **Vendedor**: vendedor@tiendavirtual.com / vendedor123
-   **Soporte**: soporte@tiendavirtual.com / soporte123
-   **Repartidor**: repartidor@tiendavirtual.com / repartidor123
-   **Clientes**: 6 clientes con límites de crédito S/ 2,500 - S/ 8,000

### Productos Incluidos

-   **iPhone**: 15 Pro Max, iPhone 14 (con variaciones de color/almacenamiento)
-   **Samsung**: Galaxy S24 Ultra, Galaxy A54
-   **Xiaomi**: 13 Pro
-   **Laptops**: ASUS ROG Strix G16 (Gaming), MacBook Air M2
-   **Tablets**: iPad Air
-   **Audio**: AirPods Pro 2da gen, Sony WH-1000XM5

### Sistema de Crédito Completo

-   **Pedidos a Crédito**: Con cuotas de 3, 6, 12, 18, 24 meses
-   **Cálculo de Intereses**: 8% anual distribuido en cuotas
-   **Estados de Cuotas**: Pendiente, Pagado, Atrasado, Condonado
-   **Mora Automática**: 5% por día de atraso
-   **Historial de Pagos**: Pagos registrados con referencias

### Interacciones de Usuario

-   **Favoritos**: 2-8 productos favoritos por cliente
-   **Comentarios**: 3-12 reseñas por producto con calificaciones realistas
-   **Direcciones**: Direcciones principales y secundarias
-   **Notificaciones**: Sistema completo de notificaciones

### Cupones de Descuento

-   **BIENVENIDO10**: 10% descuento nuevos clientes
-   **VERANO2024**: 15% promoción estacional
-   **TECH100**: S/ 100 descuento fijo tecnología
-   **CREDITO200**: S/ 200 descuento compras a crédito
-   **Y más cupones con diferentes vigencias**

## Funcionalidades del Sistema de Crédito

### 📊 Gestión de Pedidos a Crédito

-   Cálculo automático de cuotas e intereses
-   Estados realistas de pedidos (pendiente, aprobado, enviado, entregado)
-   Observaciones específicas según tipo de pago

### 💳 Sistema de Cuotas

-   Cuotas con fechas de vencimiento automáticas
-   Cálculo de mora por días de atraso
-   Estados de cuotas distribuidos realisticamente
-   Historial completo de pagos

### 🏪 Experiencia de Compra Completa

-   Productos con imágenes múltiples
-   Variaciones de productos (color, almacenamiento)
-   Sistema de reseñas con calificaciones
-   Lista de productos favoritos

## Verificar los Datos

Después de ejecutar los seeders:

```bash
php artisan tinker

# Verificar usuarios y límites de crédito
>>> App\Models\User::where('rol', 'cliente')->pluck('name', 'limite_credito')

# Verificar pedidos a crédito
>>> App\Models\Pedido::where('tipo_pago', 'credito')->with('user')->get()

# Verificar cuotas de crédito
>>> App\Models\CuotaCredito::with('pedido.user')->get()

# Verificar comentarios por producto
>>> App\Models\Comentario::with('producto', 'user')->where('aprobado', true)->get()

# Verificar variaciones de productos
>>> App\Models\VariacionProducto::with('producto')->get()
```

## Estadísticas del Sistema

Después de ejecutar todos los seeders tendrás:

-   **10 usuarios** (4 roles administrativos + 6 clientes)
-   **10 productos** con especificaciones completas
-   **39 variaciones** de productos (color/almacenamiento)
-   **40+ imágenes** de productos
-   **15-30 pedidos** (mix de contado y crédito)
-   **80+ cuotas de crédito** con estados realistas
-   **30+ productos favoritos**
-   **60+ comentarios** con calificaciones
-   **8 cupones** de descuento activos
-   **Sistema completo de filtros** avanzados

¡Tu tienda virtual está lista para simular un negocio real de venta a crédito! 🚀💳
