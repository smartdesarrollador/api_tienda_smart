<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ConfiguracionesController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\DatosFacturacionController;
use App\Http\Controllers\API\AtributoController;
use App\Http\Controllers\API\ValorAtributoController;
use App\Http\Controllers\API\CuponController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\API\VariacionProductoController;
use App\Http\Controllers\API\ImagenProductoController;
use App\Http\Controllers\API\PedidoController;
use App\Http\Controllers\API\DetallePedidoController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\API\CuotaCreditoController;
use App\Http\Controllers\API\DireccionController;
use App\Http\Controllers\API\ComentarioController;
use App\Http\Controllers\API\NotificacionController;
use App\Http\Controllers\API\FavoritoController;
use App\Http\Controllers\API\ReporteController;
use App\Http\Controllers\Api\CarritoController;
use App\Http\Controllers\Api\MetodoPagoController;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ZonaRepartoController;
use App\Http\Controllers\Api\DireccionValidadaController;
use App\Http\Controllers\API\DepartamentoController;
use App\Http\Controllers\API\ProvinciaController;
use App\Http\Controllers\API\DistritoController;
use App\Http\Controllers\Api\ZonaDistritoController;
use App\Http\Controllers\Api\HorarioZonaController;
use App\Http\Controllers\Api\CostoEnvioDinamicoController;
use App\Http\Controllers\Api\ExcepcionZonaController;
use App\Http\Controllers\Api\SeguimientoPedidoController;
use App\Http\Controllers\Api\CarritoTemporalController;
use App\Http\Controllers\Api\InventarioMovimientoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PromocionController;
use App\Http\Controllers\Api\MetricaNegocioController;
use App\Http\Controllers\Api\ProgramacionEntregaController;
use App\Http\Controllers\Api\SeoProductoController;
use App\Http\Controllers\Api\CuentaUsuarioController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

/* Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
}); */

Route::get('/', function () {
    return response()->json(['message' => 'Hola Mundo']);
});

//Ruta de prueba hola mundo para verificar que el servidor está funcionando
Route::get('/test/hola-mundo', function () {
    return response()->json(['message' => 'Hola Mundo']);
});

// Ruta de prueba para ZonaRepartoController
Route::get('/test-zona-reparto', [ZonaRepartoController::class, 'index']);

// Endpoint público para acceder a imágenes de perfil
Route::get('users/profile-image/{userId}', [AuthController::class, 'getProfileImage']);

// Rutas públicas para configuraciones
Route::get('configuraciones/todas', [ConfiguracionesController::class, 'obtenerTodas']);
Route::get('configuraciones/imagen/{clave}', [ConfiguracionesController::class, 'obtenerImagen']);

// Rutas públicas para banners
Route::get('banners', [BannerController::class, 'index']);
Route::get('banners/{id}', [BannerController::class, 'show']);

// Rutas públicas de categorías (para catálogo público)
Route::prefix('categorias')->group(function () {
    Route::get('/', [CategoriaController::class, 'index']);
    Route::get('/tree', [CategoriaController::class, 'tree']);
    Route::get('/principales', [CategoriaController::class, 'principales']);
    Route::get('/for-select', [CategoriaController::class, 'forSelect']);
    Route::get('/{id}', [CategoriaController::class, 'show']);
    Route::get('/slug/{slug}', [CategoriaController::class, 'bySlug']);
});

// Rutas públicas de productos (para catálogo público)
Route::prefix('productos')->group(function () {
    Route::get('/', [ProductoController::class, 'index']);
    Route::get('/categoria/{categoria}', [ProductoController::class, 'byCategoria']);
    Route::get('/destacados', [ProductoController::class, 'destacados']);
    Route::get('/search', [ProductoController::class, 'search']);
    Route::get('/{producto}', [ProductoController::class, 'show']);
});

// Rutas públicas de comentarios (para vista pública)
Route::prefix('comentarios')->group(function () {
    Route::get('/', [ComentarioController::class, 'index']);
    Route::get('/producto/{producto}', [ComentarioController::class, 'byProducto']);
    Route::get('/{comentario}', [ComentarioController::class, 'show']);
});

// Rutas públicas de autenticación
Route::group(['prefix' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    
    // Rutas de recuperación de contraseña
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('validate-reset-token', [AuthController::class, 'validateResetToken']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// Rutas protegidas que requieren autenticación
Route::group(['middleware' => 'jwt.verify'], function () {
    // Rutas de autenticación que requieren estar autenticado
    Route::group(['prefix' => 'auth'], function () {
        Route::get('profile', [AuthController::class, 'profile']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::put('change-password', [AuthController::class, 'changePassword']);
        Route::post('profile-image', [AuthController::class, 'uploadProfileImage']);
        // Ruta para verificar si un usuario tiene acceso al panel admin
        Route::get('check-admin-access', [UserController::class, 'checkAdminAccess']);
    });
    
    // Rutas de usuarios
    Route::apiResource('users', UserController::class);
    
    // Rutas para gestión del perfil
    Route::put('users/profile', [UserController::class, 'updateProfile']);
    Route::put('users/password', [UserController::class, 'updatePassword']);
    Route::post('users/profile-image', [UserController::class, 'uploadProfileImage']);

    // Rutas para gestión de categorías (protegidas para administración)
    Route::prefix('admin/categorias')->group(function () {
        Route::get('/', [CategoriaController::class, 'index']);
        Route::get('/validate-nombre', [CategoriaController::class, 'validateNombre']);
        Route::get('/{id}', [CategoriaController::class, 'show']);
        Route::post('/', [CategoriaController::class, 'store']);
        Route::post('/{id}/update', [CategoriaController::class, 'update']);
        Route::put('/{id}', [CategoriaController::class, 'update']); // Mantener PUT también para compatibilidad
        Route::delete('/{id}', [CategoriaController::class, 'destroy']);
        Route::post('/{id}/imagen', [CategoriaController::class, 'uploadImage']);
        Route::delete('/{id}/imagen', [CategoriaController::class, 'removeImage']);
        Route::put('/order/update', [CategoriaController::class, 'updateOrder']);
    });

    // Rutas para gestión de productos (protegidas, solo para administradores)
    Route::prefix('admin/productos')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [ProductoController::class, 'statistics']);
        Route::post('/{producto}/toggle-destacado', [ProductoController::class, 'toggleDestacado']);
        Route::post('/{producto}/toggle-activo', [ProductoController::class, 'toggleActivo']);
        Route::delete('/{producto}/imagen-principal', [ProductoController::class, 'removeImagenPrincipal']);
        Route::post('/{producto}/update', [ProductoController::class, 'update']);
    });
    Route::apiResource('admin/productos', ProductoController::class)->except(['update']);

    // Rutas para configuraciones (protegidas, solo para administradores)
    Route::prefix('configuraciones')->group(function () {
        Route::get('/', [ConfiguracionesController::class, 'index']);
        Route::get('/grupos', [ConfiguracionesController::class, 'grupos']);
        Route::get('/{id}', [ConfiguracionesController::class, 'show']);
        Route::put('/{id}', [ConfiguracionesController::class, 'update']);
        Route::post('/{id}/imagen', [ConfiguracionesController::class, 'subirImagen']);
        Route::post('/actualizar-multiple', [ConfiguracionesController::class, 'actualizarMultiple']);
    });

    // Rutas para gestión de banners (protegidas, solo para administradores)
    Route::prefix('admin/banners')->group(function () {
        Route::get('/', [BannerController::class, 'admin']);
        Route::post('/{id}', [BannerController::class, 'update']);
    });
    Route::apiResource('banners', BannerController::class)->except(['index', 'show', 'update']);
    
    // Rutas para perfil de cliente (para usuarios autenticados con rol cliente)
    Route::prefix('clientes')->group(function () {
        Route::get('/perfil', [\App\Http\Controllers\Api\ClienteController::class, 'perfil']);
        Route::get('/configuracion', [\App\Http\Controllers\Api\ClienteController::class, 'configuracion']);
        Route::put('/configuracion/privacidad', [\App\Http\Controllers\Api\ClienteController::class, 'actualizarPrivacidad']);
        Route::put('/configuracion/preferencias', [\App\Http\Controllers\Api\ClienteController::class, 'actualizarPreferencias']);
    });

    // Rutas para pedidos del cliente autenticado
    Route::prefix('pedidos')->group(function () {
        Route::get('/estadisticas', [\App\Http\Controllers\API\PedidoController::class, 'statistics']);
        Route::get('/', [\App\Http\Controllers\API\PedidoController::class, 'index']);
    });

    // Rutas para favoritos del cliente autenticado  
    Route::prefix('favoritos')->group(function () {
        Route::get('/count', [\App\Http\Controllers\API\FavoritoController::class, 'count']);
        Route::get('/', [\App\Http\Controllers\API\FavoritoController::class, 'index']);
    });

    // Rutas para direcciones del cliente autenticado
    Route::prefix('direcciones')->group(function () {
        Route::get('/count', [\App\Http\Controllers\API\DireccionController::class, 'count']);
        Route::get('/', [\App\Http\Controllers\API\DireccionController::class, 'index']);
    });

    // Rutas para el panel de cuenta del usuario cliente (vista pública)
    Route::prefix('cuenta-usuario')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'dashboard']);
        Route::get('/perfil', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'perfil']);
        Route::put('/perfil', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'actualizarPerfil']);
        Route::put('/cambiar-password', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'cambiarPassword']);
        Route::get('/pedidos', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'pedidos']);
        Route::get('/pedidos/{pedidoId}', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'mostrarPedido']);
        Route::get('/direcciones', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'direcciones']);
        Route::post('/direcciones', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'crearDireccion']);
        Route::put('/direcciones/{id}', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'actualizarDireccion']);
        Route::put('/direcciones/{id}/predeterminada', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'establecerDireccionPredeterminada']);
        Route::delete('/direcciones/{id}', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'eliminarDireccion']);
        Route::get('/favoritos', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'favoritos']);
        Route::get('/favoritos/categorias', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'categoriasFavoritos']);
        Route::post('/favoritos/toggle', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'toggleFavorito']);
        Route::get('/historial', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'historial']);
        Route::get('/notificaciones', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'notificaciones']);
        Route::put('/notificaciones/{notificacionId}/marcar-leida', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'marcarNotificacionLeida']);
        Route::put('/notificaciones/marcar-todas-leidas', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'marcarTodasNotificacionesLeidas']);
        Route::get('/credito', [\App\Http\Controllers\Api\CuentaUsuarioController::class, 'credito']);
    });

    // Rutas para gestión de clientes (protegidas, solo para administradores)
    Route::prefix('admin/clientes')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [\App\Http\Controllers\Api\ClienteController::class, 'statistics']);
        Route::post('/{cliente}/cambiar-estado', [\App\Http\Controllers\Api\ClienteController::class, 'cambiarEstado']);
        Route::post('/{cliente}/verificar', [\App\Http\Controllers\Api\ClienteController::class, 'verificar']);
    });
    Route::apiResource('admin/clientes', \App\Http\Controllers\Api\ClienteController::class);
    
    // Rutas para gestión de datos de facturación (protegidas, solo para administradores)
    Route::prefix('admin/datos-facturacion')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'statistics']);
        Route::get('/cliente/{cliente}', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'byCliente']);
        Route::post('/{datosFacturacion}/establecer-predeterminado', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'establecerPredeterminado']);
        Route::post('/{datosFacturacion}/activar', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'activar']);
        Route::post('/{datosFacturacion}/desactivar', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'desactivar']);
        Route::post('/validar-documento', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'validarDocumento']);
    });
    Route::apiResource('admin/datos-facturacion', \App\Http\Controllers\Api\DatosFacturacionController::class);
    
    // Rutas para gestión de atributos (protegidas, solo para administradores)
    Route::apiResource('admin/atributos', AtributoController::class);

    // Rutas para gestión de valores de atributos (protegidas, solo para administradores)
    Route::prefix('admin/valores-atributo')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [ValorAtributoController::class, 'statistics']);
        Route::get('/atributo/{atributo}', [ValorAtributoController::class, 'byAtributo']);
        Route::post('/atributo/{atributo}/bulk', [ValorAtributoController::class, 'bulkStore']);
        Route::delete('/{valorAtributo}/imagen', [ValorAtributoController::class, 'removeImage']);
    });
    Route::apiResource('admin/valores-atributo', ValorAtributoController::class);

    // Rutas para gestión de cupones (protegidas, solo para administradores)
    Route::apiResource('admin/cupones', CuponController::class);

    // Rutas para gestión de métodos de pago (protegidas, solo para administradores)
    Route::prefix('admin/metodos-pago')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/for-select', [MetodoPagoController::class, 'forSelect']);
        Route::post('/{metodoPago}/calcular-comision', [MetodoPagoController::class, 'calcularComision']);
        Route::get('/estadisticas', [MetodoPagoController::class, 'estadisticas']);
    });
    Route::apiResource('admin/metodos-pago', MetodoPagoController::class);

    // Rutas públicas para métodos de pago (para frontend)
    Route::prefix('metodos-pago-publicos')->group(function () {
        Route::get('/activos', [MetodoPagoController::class, 'forSelect']);
        Route::post('/{metodoPago}/calcular-comision', [MetodoPagoController::class, 'calcularComision']);
    });
    
    // Rutas para gestión de variaciones de productos (protegidas, solo para administradores)
    Route::prefix('admin/variaciones-producto')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/producto/{producto}', [VariacionProductoController::class, 'byProducto']);
        Route::post('/{variacion}/toggle-activo', [VariacionProductoController::class, 'toggleActivo']);
        Route::post('/{variacion}/update-stock', [VariacionProductoController::class, 'updateStock']);
    });
    Route::apiResource('admin/variaciones-producto', VariacionProductoController::class);
    
    // Rutas para gestión de imágenes de productos (protegidas, solo para administradores)
    Route::prefix('admin/imagenes-producto')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [ImagenProductoController::class, 'statistics']);
        Route::get('/producto/{producto}', [ImagenProductoController::class, 'byProducto']);
        Route::get('/variacion/{variacion}', [ImagenProductoController::class, 'byVariacion']);
        Route::post('/update-order', [ImagenProductoController::class, 'updateOrder']);
        Route::post('/{imagenProducto}/set-principal', [ImagenProductoController::class, 'setPrincipal']);
        Route::post('/{imagenProducto}/update', [ImagenProductoController::class, 'update']);
    });
    Route::apiResource('admin/imagenes-producto', ImagenProductoController::class)->except(['update']);
    
    // Rutas para gestión de pedidos (protegidas, solo para administradores)
    Route::prefix('admin/pedidos')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [PedidoController::class, 'statistics']);
        Route::get('/usuario/{usuario}', [PedidoController::class, 'byUsuario']);
        Route::post('/{pedido}/cambiar-estado', [PedidoController::class, 'cambiarEstado']);
        Route::post('/{pedido}/aplicar-cupon', [PedidoController::class, 'aplicarCupon']);
    });
    Route::apiResource('admin/pedidos', PedidoController::class);
    
    // Rutas para gestión de detalles de pedidos (protegidas, solo para administradores)
    Route::prefix('admin/detalles-pedido')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [DetallePedidoController::class, 'statistics']);
        Route::get('/pedido/{pedido}', [DetallePedidoController::class, 'byPedido']);
        Route::post('/{detallePedido}/update-cantidad', [DetallePedidoController::class, 'updateCantidad']);
    });
    Route::apiResource('admin/detalles-pedido', DetallePedidoController::class);
    
    // Rutas para gestión de pagos (protegidas, solo para administradores)
    Route::prefix('admin/pagos')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [PagoController::class, 'statistics']);
        Route::get('/pedido/{pedido}', [PagoController::class, 'byPedido']);
        Route::get('/metodo-pago/{metodoPago}', [PagoController::class, 'byMetodoPago']);
        Route::post('/metodo-pago/{metodoPago}/crear', [PagoController::class, 'crearConMetodoPago']);
        Route::post('/{pago}/procesar', [PagoController::class, 'procesarPago']);
        Route::post('/{pago}/cancelar', [PagoController::class, 'cancelarPago']);
    });
    Route::apiResource('admin/pagos', PagoController::class);
    
    // Rutas para gestión de cuotas de crédito (protegidas, solo para administradores)
    Route::prefix('admin/cuotas-credito')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [CuotaCreditoController::class, 'statistics']);
        Route::get('/pedido/{pedido}', [CuotaCreditoController::class, 'byPedido']);
        Route::get('/vencidas', [CuotaCreditoController::class, 'vencidas']);
        Route::post('/{cuotaCredito}/marcar-pagada', [CuotaCreditoController::class, 'marcarPagada']);
        Route::post('/{cuotaCredito}/calcular-mora', [CuotaCreditoController::class, 'calcularMora']);
    });
    Route::apiResource('admin/cuotas-credito', CuotaCreditoController::class);
    
    // Rutas para gestión de direcciones (protegidas, solo para administradores)
    Route::prefix('admin/direcciones')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [DireccionController::class, 'statistics']);
        Route::get('/usuario/{usuario}', [DireccionController::class, 'byUsuario']);
        Route::post('/{direccion}/set-predeterminada', [DireccionController::class, 'setPredeterminada']);
    });
    Route::apiResource('admin/direcciones', DireccionController::class);

    // Rutas para gestión de comentarios (protegidas, solo para administradores)
    Route::prefix('admin/comentarios')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [ComentarioController::class, 'statistics']);
        Route::get('/producto/{producto}', [ComentarioController::class, 'byProducto']);
        Route::post('/{comentario}/aprobar', [ComentarioController::class, 'aprobar']);
        Route::post('/{comentario}/rechazar', [ComentarioController::class, 'rechazar']);
        Route::post('/{comentario}/responder', [ComentarioController::class, 'responder']);
    });
    Route::apiResource('admin/comentarios', ComentarioController::class);
    
    // Rutas para gestión de notificaciones (protegidas, solo para administradores)
    Route::prefix('admin/notificaciones')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [NotificacionController::class, 'statistics']);
        Route::get('/usuario/{usuario}', [NotificacionController::class, 'byUsuario']);
        Route::post('/{notificacion}/marcar-leida', [NotificacionController::class, 'marcarLeida']);
        Route::post('/{notificacion}/marcar-no-leida', [NotificacionController::class, 'marcarNoLeida']);
        Route::post('/marcar-todas-leidas', [NotificacionController::class, 'marcarTodasLeidas']);
        Route::delete('/limpiar-antiguas', [NotificacionController::class, 'limpiarAntiguas']);
        Route::post('/enviar-masiva', [NotificacionController::class, 'enviarMasiva']);
    });
    Route::apiResource('admin/notificaciones', NotificacionController::class);

    // Rutas para gestión de favoritos (protegidas, solo para administradores)
    Route::prefix('admin/favoritos')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [FavoritoController::class, 'statistics']);
        Route::get('/usuario/{usuario}', [FavoritoController::class, 'byUsuario']);
        Route::post('/toggle', [FavoritoController::class, 'toggle']);
        Route::post('/verificar', [FavoritoController::class, 'verificarFavorito']);
        Route::delete('/limpiar', [FavoritoController::class, 'limpiarFavoritos']);
    });
    Route::apiResource('admin/favoritos', FavoritoController::class);

    // Rutas para dashboard (protegidas, solo para administradores)
    Route::prefix('admin/dashboard')->group(function () {
        // Ruta principal del dashboard con resumen general
        Route::get('/', [DashboardController::class, 'index']);
        
        // Rutas especializadas para diferentes secciones
        Route::get('/ventas', [DashboardController::class, 'ventas']);
        Route::get('/productos', [DashboardController::class, 'productos']);
        Route::get('/usuarios', [DashboardController::class, 'usuarios']);
        Route::get('/financieras', [DashboardController::class, 'financieras']);
        Route::get('/alertas', [DashboardController::class, 'alertas']);
        Route::get('/actividad', [DashboardController::class, 'actividad']);
        
        // Utilidades del dashboard
        Route::delete('/cache', [DashboardController::class, 'limpiarCache']);
    });

    // Rutas para reportes (protegidas, solo para administradores)
    Route::prefix('admin/reportes')->group(function () {
        // Ruta principal para listar reportes disponibles
        Route::get('/', [ReporteController::class, 'index']);
        
        // Rutas especializadas para diferentes tipos de reportes
        Route::get('/ventas', [ReporteController::class, 'ventas']);
        Route::get('/inventario', [ReporteController::class, 'inventario']);
        Route::get('/clientes', [ReporteController::class, 'clientes']);
        Route::get('/financiero', [ReporteController::class, 'financiero']);
        Route::post('/personalizado', [ReporteController::class, 'personalizado']);
        
        // Estadísticas generales de reportes
        Route::get('/estadisticas', [ReporteController::class, 'estadisticas']);
    });

    // Rutas para gestión de adicionales y relaciones (protegidas, solo para administradores)
    Route::apiResource('admin/adicionales', \App\Http\Controllers\Api\AdicionalController::class);
    Route::apiResource('admin/producto-adicionales', \App\Http\Controllers\Api\ProductoAdicionalController::class);
    Route::apiResource('admin/grupos-adicionales', \App\Http\Controllers\Api\GrupoAdicionalController::class);
    Route::apiResource('admin/adicional-grupos', \App\Http\Controllers\Api\AdicionalGrupoController::class);
    Route::apiResource('admin/producto-grupo-adicionales', \App\Http\Controllers\Api\ProductoGrupoAdicionalController::class);
    Route::apiResource('admin/detalle-adicionales', \App\Http\Controllers\Api\DetalleAdicionalController::class);
});

/* Route::post('register',[UserController::class,'register']);

Route::post('login',[UserController::class,'login']); */

/* Route::apiResource('users', UserController::class); */

/*
|--------------------------------------------------------------------------
| Rutas del Carrito de Compras
|--------------------------------------------------------------------------
|
| Rutas para gestión completa del carrito de compras, incluyendo items,
| cupones, envío y sincronización con el frontend.
|
*/

Route::prefix('carrito')->name('carrito.')->group(function () {
    // Obtener carrito actual
    Route::get('/', [CarritoController::class, 'index'])->name('index');
    
    // Gestión de items del carrito
    Route::post('/agregar', [CarritoController::class, 'agregar'])->name('agregar');
    Route::put('/actualizar', [CarritoController::class, 'actualizar'])->name('actualizar');
    Route::delete('/remover/{itemId}', [CarritoController::class, 'remover'])->name('remover');
    Route::delete('/limpiar', [CarritoController::class, 'limpiar'])->name('limpiar');
    
    // Gestión de cupones
    Route::post('/aplicar-cupon', [CarritoController::class, 'aplicarCupon'])->name('aplicar_cupon');
    Route::delete('/remover-cupon/{codigo}', [CarritoController::class, 'removerCupon'])->name('remover_cupon');
    
    // Cálculo de envío
    Route::post('/calcular-envio', [CarritoController::class, 'calcularEnvio'])->name('calcular_envio');
    
    // Verificación de stock y disponibilidad
    Route::post('/verificar-stock', [CarritoController::class, 'verificarStock'])->name('verificar_stock');
    
    // Productos relacionados para carrito vacío
    Route::get('/productos-relacionados', [CarritoController::class, 'productosRelacionados'])->name('productos_relacionados');
    
    // Sincronización con frontend
    Route::post('/sincronizar', [CarritoController::class, 'sincronizar'])->name('sincronizar');
    
    // Configuración del carrito
    Route::get('/configuracion', [CarritoController::class, 'configuracion'])->name('configuracion');
});

/*
|--------------------------------------------------------------------------
| Rutas para los nuevos controladores de la vista pública
|--------------------------------------------------------------------------
|
| Rutas para checkout, métodos de envío/pago, contacto, FAQ, newsletter y búsqueda
|
*/

// Rutas para proceso de checkout
Route::prefix('checkout')->group(function () {
    Route::post('/iniciar', [CheckoutController::class, 'iniciarCheckout']);
    Route::post('/validar-datos-personales', [CheckoutController::class, 'validarDatosPersonales']);
    Route::post('/validar-direccion-envio', [CheckoutController::class, 'validarDireccionEnvio']);
    Route::post('/calcular-envio', [CheckoutController::class, 'calcularEnvio']);
    Route::post('/aplicar-cupon', [CheckoutController::class, 'aplicarCupon']);
    Route::post('/procesar-pedido', [CheckoutController::class, 'procesarPedido']);
    Route::get('/resumen', [CheckoutController::class, 'obtenerResumen']);
    Route::get('/configuracion', [CheckoutController::class, 'obtenerConfiguracion']);
    Route::get('/metodos-pago', [CheckoutController::class, 'obtenerMetodosPago']);
    Route::post('/validar-metodo-pago', [CheckoutController::class, 'validarMetodoPago']);
    
    // Rutas específicas de Izipay
    Route::prefix('izipay')->group(function () {
        Route::post('/generar-formtoken', [CheckoutController::class, 'generarFormTokenIzipay']);
        Route::post('/validar-pago', [CheckoutController::class, 'validarPagoIzipay']);
        Route::get('/configuracion', [CheckoutController::class, 'verificarConfiguracionIzipay']);
        // Ruta de prueba para verificar credenciales
        Route::get('/test', [CheckoutController::class, 'testIzipayConfig']);
        // Ruta para simular pago exitoso en pruebas
        Route::post('/simular-pago-exitoso', [CheckoutController::class, 'simularPagoExitoso']);
    });
});

// Rutas para métodos de envío
Route::prefix('metodos-envio')->name('metodos_envio.')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\MetodoEnvioController::class, 'index'])->name('index');
    Route::get('/{id}', [App\Http\Controllers\Api\MetodoEnvioController::class, 'show'])->name('show');
    Route::post('/calcular-costo', [App\Http\Controllers\Api\MetodoEnvioController::class, 'calcularCosto'])->name('calcular_costo');
    Route::get('/zonas/cobertura', [App\Http\Controllers\Api\MetodoEnvioController::class, 'zonasCobertura'])->name('zonas_cobertura');
});

// Rutas para métodos de pago
Route::prefix('metodos-pago')->name('metodos_pago.')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\MetodoPagoController::class, 'index'])->name('index');
    Route::get('/{id}', [App\Http\Controllers\Api\MetodoPagoController::class, 'show'])->name('show');
    Route::post('/validar-tarjeta', [App\Http\Controllers\Api\MetodoPagoController::class, 'validarTarjeta'])->name('validar_tarjeta');
    Route::get('/config/comisiones', [App\Http\Controllers\Api\MetodoPagoController::class, 'comisiones'])->name('comisiones');
});

// Rutas para contacto
Route::prefix('contacto')->name('contacto.')->group(function () {
    Route::post('/enviar-mensaje', [App\Http\Controllers\Api\ContactoController::class, 'enviarMensaje'])->name('enviar_mensaje');
    Route::get('/informacion-empresa', [App\Http\Controllers\Api\ContactoController::class, 'informacionEmpresa'])->name('informacion_empresa');
    Route::get('/tipos-consulta', [App\Http\Controllers\Api\ContactoController::class, 'tiposConsulta'])->name('tipos_consulta');
    Route::get('/faq', [App\Http\Controllers\Api\ContactoController::class, 'faqContacto'])->name('faq_contacto');
    Route::get('/estado-servicio', [App\Http\Controllers\Api\ContactoController::class, 'estadoServicio'])->name('estado_servicio');
});

// Rutas para preguntas frecuentes
Route::prefix('faq')->name('faq.')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\PreguntasFrecuentesController::class, 'index'])->name('index');
    Route::get('/categorias', [App\Http\Controllers\Api\PreguntasFrecuentesController::class, 'categorias'])->name('categorias');
    Route::get('/categoria/{categoria}', [App\Http\Controllers\Api\PreguntasFrecuentesController::class, 'porCategoria'])->name('por_categoria');
    Route::get('/buscar', [App\Http\Controllers\Api\PreguntasFrecuentesController::class, 'buscar'])->name('buscar');
    Route::get('/estadisticas', [App\Http\Controllers\Api\PreguntasFrecuentesController::class, 'estadisticas'])->name('estadisticas');
    Route::get('/{id}', [App\Http\Controllers\Api\PreguntasFrecuentesController::class, 'show'])->name('show');
    Route::post('/{id}/marcar-util', [App\Http\Controllers\Api\PreguntasFrecuentesController::class, 'marcarUtil'])->name('marcar_util');
    Route::post('/sugerir-pregunta', [App\Http\Controllers\Api\PreguntasFrecuentesController::class, 'sugerirPregunta'])->name('sugerir_pregunta');
});

// Rutas para newsletter
Route::prefix('newsletter')->name('newsletter.')->group(function () {
    Route::post('/suscribirse', [App\Http\Controllers\Api\NewsletterController::class, 'suscribirse'])->name('suscribirse');
    Route::get('/confirmar/{token}', [App\Http\Controllers\Api\NewsletterController::class, 'confirmar'])->name('confirmar');
    Route::post('/desuscribirse', [App\Http\Controllers\Api\NewsletterController::class, 'desuscribirse'])->name('desuscribirse');
    Route::get('/preferencias/{email}', [App\Http\Controllers\Api\NewsletterController::class, 'obtenerPreferencias'])->name('obtener_preferencias');
    Route::put('/preferencias', [App\Http\Controllers\Api\NewsletterController::class, 'actualizarPreferencias'])->name('actualizar_preferencias');
    Route::get('/estadisticas', [App\Http\Controllers\Api\NewsletterController::class, 'estadisticas'])->name('estadisticas');
    Route::get('/tipos-intereses', [App\Http\Controllers\Api\NewsletterController::class, 'tiposIntereses'])->name('tipos_intereses');
});

// Rutas para búsqueda
Route::prefix('busqueda')->name('busqueda.')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\BusquedaController::class, 'buscar'])->name('buscar');
    Route::post('/avanzada', [App\Http\Controllers\Api\BusquedaController::class, 'busquedaAvanzada'])->name('avanzada');
    Route::get('/autocompletar', [App\Http\Controllers\Api\BusquedaController::class, 'autocompletar'])->name('autocompletar');
    Route::get('/terminos-populares', [App\Http\Controllers\Api\BusquedaController::class, 'terminosPopulares'])->name('terminos_populares');
    Route::get('/filtros-disponibles', [App\Http\Controllers\Api\BusquedaController::class, 'filtrosDisponibles'])->name('filtros_disponibles');
    Route::get('/estadisticas', [App\Http\Controllers\Api\BusquedaController::class, 'estadisticas'])->name('estadisticas');
});

/*
|--------------------------------------------------------------------------
| Rutas para Zonas de Reparto y Direcciones Validadas
|--------------------------------------------------------------------------
|
| Rutas para gestión completa de zonas de reparto y validación de direcciones
| para el sistema de delivery.
|
*/

// Rutas públicas para zonas de reparto (para consulta del frontend)
Route::prefix('zonas-reparto')->group(function () {
    Route::get('/', [ZonaRepartoController::class, 'index']);
    Route::get('/distritos/disponibles', [ZonaRepartoController::class, 'getDistritosDisponibles']);
    Route::get('/{zonaReparto}', [ZonaRepartoController::class, 'show']);
    Route::get('/{zonaReparto}/horarios', [ZonaRepartoController::class, 'getHorarios']);
    Route::post('/{zonaReparto}/calcular-costo', [ZonaRepartoController::class, 'calcularCostoEnvio']);
    Route::post('/{zonaReparto}/verificar-disponibilidad', [ZonaRepartoController::class, 'verificarDisponibilidad']);
});

// Rutas protegidas para administración de zonas de reparto
Route::middleware('jwt.verify')->group(function () {
    Route::prefix('admin/zonas-reparto')->group(function () {
        // Rutas especiales antes del resource
        Route::get('/distritos/disponibles', [ZonaRepartoController::class, 'getDistritosDisponibles']);
        Route::get('/{zonaReparto}/estadisticas', [ZonaRepartoController::class, 'getEstadisticas']);
        Route::post('/{zonaReparto}/toggle-status', [ZonaRepartoController::class, 'toggleStatus']);
    });
    Route::apiResource('admin/zonas-reparto', ZonaRepartoController::class);
});

// Rutas para direcciones validadas
Route::middleware('jwt.verify')->group(function () {
    // Rutas públicas para validación
    Route::prefix('direcciones-validadas')->group(function () {
        Route::post('/validar', [DireccionValidadaController::class, 'validarDireccion']);
        Route::get('/estadisticas', [DireccionValidadaController::class, 'getEstadisticas']);
    });

    // Rutas administrativas para direcciones validadas
    Route::prefix('admin/direcciones-validadas')->group(function () {
        // Rutas especiales antes del resource
        Route::post('/revalidar', [DireccionValidadaController::class, 'revalidarDirecciones']);
        Route::get('/estadisticas', [DireccionValidadaController::class, 'getEstadisticas']);
    });
    Route::apiResource('admin/direcciones-validadas', DireccionValidadaController::class);
});

/*
|--------------------------------------------------------------------------
| Rutas para División Territorial (Departamentos, Provincias, Distritos)
|--------------------------------------------------------------------------
|
| Rutas para gestión completa de la división territorial del país, 
| incluyendo consultas públicas y administración protegida.
|
*/

// Rutas públicas para consulta de división territorial
Route::prefix('ubicacion')->group(function () {
    // Departamentos
    Route::prefix('departamentos')->group(function () {
        Route::get('/', [App\Http\Controllers\API\DepartamentoController::class, 'index']);
        Route::get('/{departamento}', [App\Http\Controllers\API\DepartamentoController::class, 'show']);
        Route::get('/pais/{pais}', [App\Http\Controllers\API\DepartamentoController::class, 'porPais']);
        Route::get('/estadisticas/generales', [App\Http\Controllers\API\DepartamentoController::class, 'estadisticas']);
    });

    // Provincias  
    Route::prefix('provincias')->group(function () {
        Route::get('/', [App\Http\Controllers\API\ProvinciaController::class, 'index']);
        Route::get('/{provincia}', [App\Http\Controllers\API\ProvinciaController::class, 'show']);
        Route::get('/departamento/{departamento}', [App\Http\Controllers\API\ProvinciaController::class, 'porDepartamento']);
        Route::get('/estadisticas/generales', [App\Http\Controllers\API\ProvinciaController::class, 'estadisticas']);
    });

    // Distritos
    Route::prefix('distritos')->group(function () {
        Route::get('/', [App\Http\Controllers\API\DistritoController::class, 'index']);
        Route::get('/{distrito}', [App\Http\Controllers\API\DistritoController::class, 'show']);
        Route::get('/provincia/{provincia}', [App\Http\Controllers\API\DistritoController::class, 'porProvincia']);
        Route::get('/delivery/disponibles', [App\Http\Controllers\API\DistritoController::class, 'disponiblesDelivery']);
        Route::post('/buscar/coordenadas', [App\Http\Controllers\API\DistritoController::class, 'buscarPorCoordenadas']);
        Route::get('/estadisticas/generales', [App\Http\Controllers\API\DistritoController::class, 'estadisticas']);
    });
});

// Rutas protegidas para administración de división territorial
Route::middleware('jwt.verify')->group(function () {
    // Administración de Departamentos
    Route::prefix('admin/departamentos')->group(function () {
        // Rutas especiales antes del resource
        Route::post('/{departamento}/toggle-status', [App\Http\Controllers\API\DepartamentoController::class, 'toggleStatus']);
        Route::get('/pais/{pais}', [App\Http\Controllers\API\DepartamentoController::class, 'porPais']);
        Route::get('/estadisticas/completas', [App\Http\Controllers\API\DepartamentoController::class, 'estadisticas']);
    });
    Route::apiResource('admin/departamentos', App\Http\Controllers\API\DepartamentoController::class);

    // Administración de Provincias
    Route::prefix('admin/provincias')->group(function () {
        // Rutas especiales antes del resource
        Route::post('/{provincia}/toggle-status', [App\Http\Controllers\API\ProvinciaController::class, 'toggleStatus']);
        Route::get('/departamento/{departamento}', [App\Http\Controllers\API\ProvinciaController::class, 'porDepartamento']);
        Route::get('/estadisticas/completas', [App\Http\Controllers\API\ProvinciaController::class, 'estadisticas']);
    });
    Route::apiResource('admin/provincias', App\Http\Controllers\API\ProvinciaController::class);

    // Administración de Distritos
    Route::prefix('admin/distritos')->group(function () {
        // Rutas especiales antes del resource
        Route::post('/{distrito}/toggle-status', [App\Http\Controllers\API\DistritoController::class, 'toggleStatus']);
        Route::post('/{distrito}/toggle-delivery', [App\Http\Controllers\API\DistritoController::class, 'toggleDelivery']);
        Route::get('/provincia/{provincia}', [App\Http\Controllers\API\DistritoController::class, 'porProvincia']);
        Route::get('/delivery/disponibles', [App\Http\Controllers\API\DistritoController::class, 'disponiblesDelivery']);
        Route::post('/buscar/coordenadas', [App\Http\Controllers\API\DistritoController::class, 'buscarPorCoordenadas']);
        Route::get('/estadisticas/completas', [App\Http\Controllers\API\DistritoController::class, 'estadisticas']);
    });
    Route::apiResource('admin/distritos', App\Http\Controllers\API\DistritoController::class);
});

// Rutas para Zonas Distrito
Route::apiResource('zonas-distrito', ZonaDistritoController::class);

// Rutas para Horarios de Zona
Route::apiResource('horarios-zona', HorarioZonaController::class);

// Rutas para Costos de Envío Dinámicos
Route::apiResource('costos-envio-dinamicos', CostoEnvioDinamicoController::class);

// Rutas para Excepciones de Zona
Route::apiResource('excepciones-zona', ExcepcionZonaController::class);

// Rutas para Seguimiento de Pedidos
Route::apiResource('seguimiento-pedidos', SeguimientoPedidoController::class);
Route::put('seguimiento-pedidos/{id}/marcar-notificado', [SeguimientoPedidoController::class, 'marcarNotificado']);

// Rutas para el Carrito Temporal
Route::prefix('carrito-temporal')->group(function () {
    Route::get('/', [CarritoTemporalController::class, 'index']);
    Route::post('/', [CarritoTemporalController::class, 'store']);
    Route::get('/{carritoTemporal}', [CarritoTemporalController::class, 'show']);
    Route::put('/{carritoTemporal}', [CarritoTemporalController::class, 'update']);
    Route::delete('/{carritoTemporal}', [CarritoTemporalController::class, 'destroy']);
    Route::delete('/limpiar', [CarritoTemporalController::class, 'limpiarCarrito']);
    Route::delete('/limpiar-expirados', [CarritoTemporalController::class, 'limpiarExpirados']);
});

// Rutas para Movimientos de Inventario
Route::prefix('inventario')->group(function () {
    Route::get('/movimientos', [InventarioMovimientoController::class, 'index']);
    Route::post('/movimientos', [InventarioMovimientoController::class, 'store']);
    Route::get('/movimientos/{inventarioMovimiento}', [InventarioMovimientoController::class, 'show']);
    Route::get('/reporte', [InventarioMovimientoController::class, 'obtenerReporte']);
});

// Rutas para Promociones
Route::prefix('promociones')->group(function () {
    Route::get('/', [PromocionController::class, 'index']);
    Route::post('/', [PromocionController::class, 'store']);
    Route::get('/{promocion}', [PromocionController::class, 'show']);
    Route::put('/{promocion}', [PromocionController::class, 'update']);
    Route::delete('/{promocion}', [PromocionController::class, 'destroy']);
    Route::post('/{promocion}/toggle-activo', [PromocionController::class, 'toggleActivo']);
    Route::post('/aplicar', [PromocionController::class, 'aplicarPromocion']);
});

// Rutas para Métricas de Negocio
Route::prefix('metricas-negocio')->group(function () {
    Route::get('/', [MetricaNegocioController::class, 'index']);
    Route::post('/', [MetricaNegocioController::class, 'store']);
    Route::get('/{metricaNegocio}', [MetricaNegocioController::class, 'show']);
    Route::put('/{metricaNegocio}', [MetricaNegocioController::class, 'update']);
    Route::delete('/{metricaNegocio}', [MetricaNegocioController::class, 'destroy']);
    Route::get('/resumen/periodo', [MetricaNegocioController::class, 'resumenPeriodo']);
    Route::post('/generar/diarias', [MetricaNegocioController::class, 'generarMetricasDiarias']);
});

// Rutas para Programación de Entregas
Route::prefix('programacion-entregas')->group(function () {
    Route::get('/', [ProgramacionEntregaController::class, 'index']);
    Route::post('/', [ProgramacionEntregaController::class, 'store']);
    Route::get('/{programacionEntrega}', [ProgramacionEntregaController::class, 'show']);
    Route::put('/{programacionEntrega}', [ProgramacionEntregaController::class, 'update']);
    Route::delete('/{programacionEntrega}', [ProgramacionEntregaController::class, 'destroy']);
    Route::post('/{programacionEntrega}/cambiar-estado', [ProgramacionEntregaController::class, 'cambiarEstado']);
    Route::get('/repartidor/ruta', [ProgramacionEntregaController::class, 'rutaRepartidor']);
    Route::post('/{programacionEntrega}/reprogramar', [ProgramacionEntregaController::class, 'reprogramar']);
});

// Rutas para SEO de Productos
Route::prefix('seo-productos')->group(function () {
    Route::get('/', [SeoProductoController::class, 'index']);
    Route::post('/', [SeoProductoController::class, 'store']);
    Route::get('/{seoProducto}', [SeoProductoController::class, 'show']);
    Route::put('/{seoProducto}', [SeoProductoController::class, 'update']);
    Route::delete('/{seoProducto}', [SeoProductoController::class, 'destroy']);
    Route::post('/generar-automatico', [SeoProductoController::class, 'generarAutomatico']);
    Route::get('/{seoProducto}/analizar', [SeoProductoController::class, 'analizarSeo']);
    Route::post('/optimizar-masivo', [SeoProductoController::class, 'optimizarMasivo']);
});


/* otras rutas para tienda virtual - vista publico */



// Rutas para gestión de categorías (vista publica)
    Route::prefix('vista/categorias')->group(function () {
        Route::get('/', [CategoriaController::class, 'index']);
        Route::get('/validate-nombre', [CategoriaController::class, 'validateNombre']);
        Route::get('/{id}', [CategoriaController::class, 'show']);
        Route::post('/', [CategoriaController::class, 'store']);
        Route::post('/{id}/update', [CategoriaController::class, 'update']);
        Route::put('/{id}', [CategoriaController::class, 'update']); // Mantener PUT también para compatibilidad
        Route::delete('/{id}', [CategoriaController::class, 'destroy']);
        Route::post('/{id}/imagen', [CategoriaController::class, 'uploadImage']);
        Route::delete('/{id}/imagen', [CategoriaController::class, 'removeImage']);
        Route::put('/order/update', [CategoriaController::class, 'updateOrder']);
    });

    // Rutas para gestión de productos (vista, publica)
    Route::prefix('vista/productos')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [ProductoController::class, 'statistics']);
        Route::post('/{producto}/toggle-destacado', [ProductoController::class, 'toggleDestacado']);
        Route::post('/{producto}/toggle-activo', [ProductoController::class, 'toggleActivo']);
        Route::delete('/{producto}/imagen-principal', [ProductoController::class, 'removeImagenPrincipal']);
        Route::post('/{producto}/update', [ProductoController::class, 'update']);
    });
    Route::apiResource('vista/productos', ProductoController::class)->except(['update']);

// Rutas para gestión de banners (vista, publica)
    Route::prefix('vista/banners')->group(function () {
        Route::get('/', [BannerController::class, 'admin']);
        Route::post('/{id}', [BannerController::class, 'update']);
    });
    Route::apiResource('banners', BannerController::class)->except(['index', 'show', 'update']);

// Rutas para gestión de clientes (vista, publica)
    Route::prefix('vista/clientes')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [\App\Http\Controllers\Api\ClienteController::class, 'statistics']);
        Route::post('/{cliente}/cambiar-estado', [\App\Http\Controllers\Api\ClienteController::class, 'cambiarEstado']);
        Route::post('/{cliente}/verificar', [\App\Http\Controllers\Api\ClienteController::class, 'verificar']);
    });
    Route::apiResource('vista/clientes', \App\Http\Controllers\Api\ClienteController::class);

// Rutas para gestión de datos de facturación (vista, publica)
    Route::prefix('vista/datos-facturacion')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'statistics']);
        Route::get('/cliente/{cliente}', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'byCliente']);
        Route::post('/{datosFacturacion}/establecer-predeterminado', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'establecerPredeterminado']);
        Route::post('/{datosFacturacion}/activar', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'activar']);
        Route::post('/{datosFacturacion}/desactivar', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'desactivar']);
        Route::post('/validar-documento', [\App\Http\Controllers\Api\DatosFacturacionController::class, 'validarDocumento']);
    });
    Route::apiResource('vista/datos-facturacion', \App\Http\Controllers\Api\DatosFacturacionController::class);
    
    // Rutas para gestión de atributos (vista, publica)
    Route::apiResource('vista/atributos', AtributoController::class);

Route::prefix('vista/valores-atributo')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [ValorAtributoController::class, 'statistics']);
        Route::get('/atributo/{atributo}', [ValorAtributoController::class, 'byAtributo']);
        Route::post('/atributo/{atributo}/bulk', [ValorAtributoController::class, 'bulkStore']);
        Route::delete('/{valorAtributo}/imagen', [ValorAtributoController::class, 'removeImage']);
    });
    Route::apiResource('vista/valores-atributo', ValorAtributoController::class);

    // Rutas para gestión de cupones (vista, publica)
    Route::apiResource('vista/cupones', CuponController::class);

    // Rutas para gestión de métodos de pago (vista, publica)
    Route::prefix('vista/metodos-pago')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/for-select', [MetodoPagoController::class, 'forSelect']);
        Route::post('/{metodoPago}/calcular-comision', [MetodoPagoController::class, 'calcularComision']);
        Route::get('/estadisticas', [MetodoPagoController::class, 'estadisticas']);
    });
    Route::apiResource('vista/metodos-pago', MetodoPagoController::class);

// Rutas para gestión de variaciones de productos (vista, publica)
    Route::prefix('vista/variaciones-producto')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/producto/{producto}', [VariacionProductoController::class, 'byProducto']);
        Route::post('/{variacion}/toggle-activo', [VariacionProductoController::class, 'toggleActivo']);
        Route::post('/{variacion}/update-stock', [VariacionProductoController::class, 'updateStock']);
    });
    Route::apiResource('vista/variaciones-producto', VariacionProductoController::class);
    
    // Rutas para gestión de imágenes de productos (vista, publica)
    Route::prefix('vista/imagenes-producto')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [ImagenProductoController::class, 'statistics']);
        Route::get('/producto/{producto}', [ImagenProductoController::class, 'byProducto']);
        Route::get('/variacion/{variacion}', [ImagenProductoController::class, 'byVariacion']);
        Route::post('/update-order', [ImagenProductoController::class, 'updateOrder']);
        Route::post('/{imagenProducto}/set-principal', [ImagenProductoController::class, 'setPrincipal']);
        Route::post('/{imagenProducto}/update', [ImagenProductoController::class, 'update']);
    });
    Route::apiResource('vista/imagenes-producto', ImagenProductoController::class)->except(['update']);

// Rutas para gestión de pedidos (vista, publica)
    Route::prefix('vista/pedidos')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [PedidoController::class, 'statistics']);
        Route::get('/usuario/{usuario}', [PedidoController::class, 'byUsuario']);
        Route::post('/{pedido}/cambiar-estado', [PedidoController::class, 'cambiarEstado']);
        Route::post('/{pedido}/aplicar-cupon', [PedidoController::class, 'aplicarCupon']);
    });
    Route::apiResource('vista/pedidos', PedidoController::class);
    
    // Rutas para gestión de detalles de pedidos (vista, publica)
    Route::prefix('vista/detalles-pedido')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [DetallePedidoController::class, 'statistics']);
        Route::get('/pedido/{pedido}', [DetallePedidoController::class, 'byPedido']);
        Route::post('/{detallePedido}/update-cantidad', [DetallePedidoController::class, 'updateCantidad']);
    });
    Route::apiResource('vista/detalles-pedido', DetallePedidoController::class);

// Rutas para gestión de pagos (vista, publica)
    Route::prefix('vista/pagos')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [PagoController::class, 'statistics']);
        Route::get('/pedido/{pedido}', [PagoController::class, 'byPedido']);
        Route::get('/metodo-pago/{metodoPago}', [PagoController::class, 'byMetodoPago']);
        Route::post('/metodo-pago/{metodoPago}/crear', [PagoController::class, 'crearConMetodoPago']);
        Route::post('/{pago}/procesar', [PagoController::class, 'procesarPago']);
        Route::post('/{pago}/cancelar', [PagoController::class, 'cancelarPago']);
    });
    Route::apiResource('vista/pagos', PagoController::class);
    
    // Rutas para gestión de cuotas de crédito (vista, publica)
    Route::prefix('vista/cuotas-credito')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [CuotaCreditoController::class, 'statistics']);
        Route::get('/pedido/{pedido}', [CuotaCreditoController::class, 'byPedido']);
        Route::get('/vencidas', [CuotaCreditoController::class, 'vencidas']);
        Route::post('/{cuotaCredito}/marcar-pagada', [CuotaCreditoController::class, 'marcarPagada']);
        Route::post('/{cuotaCredito}/calcular-mora', [CuotaCreditoController::class, 'calcularMora']);
    });
    Route::apiResource('vista/cuotas-credito', CuotaCreditoController::class);
    
    // Rutas para gestión de direcciones (vista, publica)
    Route::prefix('vista/direcciones')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [DireccionController::class, 'statistics']);
        Route::get('/usuario/{usuario}', [DireccionController::class, 'byUsuario']);
        Route::post('/{direccion}/set-predeterminada', [DireccionController::class, 'setPredeterminada']);
    });
    Route::apiResource('vista/direcciones', DireccionController::class);

    // Rutas para gestión de comentarios (vista, publica)
    Route::prefix('vista/comentarios')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [ComentarioController::class, 'statistics']);
        Route::get('/producto/{producto}', [ComentarioController::class, 'byProducto']);
        Route::post('/{comentario}/aprobar', [ComentarioController::class, 'aprobar']);
        Route::post('/{comentario}/rechazar', [ComentarioController::class, 'rechazar']);
        Route::post('/{comentario}/responder', [ComentarioController::class, 'responder']);
    });
    Route::apiResource('vista/comentarios', ComentarioController::class);
    
    // Rutas para gestión de notificaciones (vista, publica)
    Route::prefix('vista/notificaciones')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [NotificacionController::class, 'statistics']);
        Route::get('/usuario/{usuario}', [NotificacionController::class, 'byUsuario']);
        Route::post('/{notificacion}/marcar-leida', [NotificacionController::class, 'marcarLeida']);
        Route::post('/{notificacion}/marcar-no-leida', [NotificacionController::class, 'marcarNoLeida']);
        Route::post('/marcar-todas-leidas', [NotificacionController::class, 'marcarTodasLeidas']);
        Route::delete('/limpiar-antiguas', [NotificacionController::class, 'limpiarAntiguas']);
        Route::post('/enviar-masiva', [NotificacionController::class, 'enviarMasiva']);
    });
    Route::apiResource('vista/notificaciones', NotificacionController::class);

    // Rutas para gestión de favoritos (vista, publica)
    Route::prefix('vista/favoritos')->group(function () {
        // Rutas especiales antes de las rutas del resource
        Route::get('/statistics', [FavoritoController::class, 'statistics']);
        Route::get('/usuario/{usuario}', [FavoritoController::class, 'byUsuario']);
        Route::post('/toggle', [FavoritoController::class, 'toggle']);
        Route::post('/verificar', [FavoritoController::class, 'verificarFavorito']);
        Route::delete('/limpiar', [FavoritoController::class, 'limpiarFavoritos']);
    });
    Route::apiResource('vista/favoritos', FavoritoController::class);

// Rutas para dashboard (vista, publica)
    Route::prefix('vista/dashboard')->group(function () {
        // Ruta principal del dashboard con resumen general
        Route::get('/', [DashboardController::class, 'index']);
        
        // Rutas especializadas para diferentes secciones
        Route::get('/ventas', [DashboardController::class, 'ventas']);
        Route::get('/productos', [DashboardController::class, 'productos']);
        Route::get('/usuarios', [DashboardController::class, 'usuarios']);
        Route::get('/financieras', [DashboardController::class, 'financieras']);
        Route::get('/alertas', [DashboardController::class, 'alertas']);
        Route::get('/actividad', [DashboardController::class, 'actividad']);
        
        // Utilidades del dashboard
        Route::delete('/cache', [DashboardController::class, 'limpiarCache']);
    });

    // Rutas para reportes (vista, publica)
    Route::prefix('vista/reportes')->group(function () {
        // Ruta principal para listar reportes disponibles
        Route::get('/', [ReporteController::class, 'index']);
        
        // Rutas especializadas para diferentes tipos de reportes
        Route::get('/ventas', [ReporteController::class, 'ventas']);
        Route::get('/inventario', [ReporteController::class, 'inventario']);
        Route::get('/clientes', [ReporteController::class, 'clientes']);
        Route::get('/financiero', [ReporteController::class, 'financiero']);
        Route::post('/personalizado', [ReporteController::class, 'personalizado']);
        
        // Estadísticas generales de reportes
        Route::get('/estadisticas', [ReporteController::class, 'estadisticas']);
    });

// Rutas para gestión de adicionales y relaciones (vista, publica)
    Route::apiResource('vista/adicionales', \App\Http\Controllers\Api\AdicionalController::class);
    Route::apiResource('vista/producto-adicionales', \App\Http\Controllers\Api\ProductoAdicionalController::class);
    Route::apiResource('vista/grupos-adicionales', \App\Http\Controllers\Api\GrupoAdicionalController::class);
    Route::apiResource('vista/adicional-grupos', \App\Http\Controllers\Api\AdicionalGrupoController::class);
    Route::apiResource('vista/producto-grupo-adicionales', \App\Http\Controllers\Api\ProductoGrupoAdicionalController::class);
    Route::apiResource('vista/detalle-adicionales', \App\Http\Controllers\Api\DetalleAdicionalController::class);


























