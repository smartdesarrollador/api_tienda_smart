<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Direccion;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Cupon;
use App\Models\Cliente;
use App\Models\User;
use App\Models\MetodoPago;
use App\Services\IzipayService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;

class CheckoutController extends Controller
{
    protected IzipayService $izipayService;

    public function __construct(IzipayService $izipayService)
    {
        $this->izipayService = $izipayService;
    }
    /**
     * Obtener métodos de pago disponibles
     */
    public function obtenerMetodosPago(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'monto' => 'nullable|numeric|min:0',
                'pais' => 'nullable|string|size:2',
                'moneda' => 'nullable|string|size:3'
            ]);

            $monto = $request->input('monto');
            $pais = $request->input('pais', 'PE'); // Por defecto Perú
            $moneda = $request->input('moneda', 'PEN'); // Por defecto soles

            $query = MetodoPago::activo()
                ->disponibleEnPais($pais)
                ->soportaMoneda($moneda)
                ->ordenados();

            // Filtrar por monto si se proporciona
            if ($monto) {
                $query->disponibleParaMonto($monto);
            }

            $metodosPago = $query->get();

            // Calcular comisiones si hay monto
            $metodosConComision = $metodosPago->map(function ($metodo) use ($monto) {
                $data = $metodo->toArray();
                
                if ($monto) {
                    $data['comision_calculada'] = $metodo->calcularComision($monto);
                    $data['monto_total_con_comision'] = $monto + $data['comision_calculada'];
                }
                
                $data['logo_url'] = $metodo->logo_url;
                $data['tiempo_procesamiento_texto'] = $metodo->getTiempoProcesamiento();
                $data['es_tarjeta'] = $metodo->esTarjeta();
                $data['es_billetera_digital'] = $metodo->esBilleteraDigital();
                $data['es_transferencia'] = $metodo->esTransferencia();
                $data['es_efectivo'] = $metodo->esEfectivo();
                
                return $data;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'metodos_pago' => $metodosConComision,
                    'total_disponibles' => $metodosConComision->count(),
                    'filtros_aplicados' => [
                        'monto' => $monto,
                        'pais' => $pais,
                        'moneda' => $moneda
                    ]
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al obtener métodos de pago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Validar método de pago específico
     */
    public function validarMetodoPago(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'metodo_pago_id' => 'required|exists:metodos_pago,id',
                'monto' => 'required|numeric|min:0.01',
                'pais' => 'nullable|string|size:2',
                'moneda' => 'nullable|string|size:3'
            ]);

            $metodoPago = MetodoPago::findOrFail($request->input('metodo_pago_id'));
            $monto = $request->input('monto');
            $pais = $request->input('pais', 'PE');
            $moneda = $request->input('moneda', 'PEN');

            // Validaciones
            $errores = [];

            if (!$metodoPago->activo) {
                $errores[] = 'El método de pago no está disponible actualmente';
            }

            if (!$metodoPago->estaDisponibleParaMonto($monto)) {
                $montoMin = $metodoPago->monto_minimo ? 'S/' . number_format($metodoPago->monto_minimo, 2) : 'sin mínimo';
                $montoMax = $metodoPago->monto_maximo ? 'S/' . number_format($metodoPago->monto_maximo, 2) : 'sin máximo';
                $errores[] = "El monto debe estar entre {$montoMin} y {$montoMax}";
            }

            if (!$metodoPago->estaDisponibleEnPais($pais)) {
                $errores[] = 'El método de pago no está disponible en tu país';
            }

            if ($metodoPago->moneda_soportada !== $moneda) {
                $errores[] = 'El método de pago no soporta la moneda seleccionada';
            }

            if (!empty($errores)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Método de pago no válido',
                    'errors' => $errores
                ], 422);
            }

            // Calcular información del pago
            $comision = $metodoPago->calcularComision($monto);
            $montoTotalConComision = $monto + $comision;

            return response()->json([
                'success' => true,
                'data' => [
                    'metodo_pago' => $metodoPago,
                    'monto_original' => $monto,
                    'comision' => $comision,
                    'monto_total_con_comision' => $montoTotalConComision,
                    'tiempo_procesamiento' => $metodoPago->getTiempoProcesamiento(),
                    'requiere_verificacion' => $metodoPago->requiereVerificacion(),
                    'permite_cuotas' => $metodoPago->permite_cuotas,
                    'cuotas_maximas' => $metodoPago->cuotas_maximas,
                    'instrucciones' => $metodoPago->instrucciones
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al validar método de pago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Iniciar proceso de checkout
     */
    public function iniciar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.producto_id' => 'required|exists:productos,id',
                'items.*.cantidad' => 'required|integer|min:1',
                'items.*.variacion_id' => 'nullable|exists:variaciones_productos,id',
                'pais' => 'nullable|string|size:2',
                'moneda' => 'nullable|string|size:3'
            ]);

            // Verificar stock y calcular totales
            $items = $request->input('items');
            $pais = $request->input('pais', 'PE');
            $moneda = $request->input('moneda', 'PEN');
            $subtotal = 0;
            $itemsValidados = [];

            foreach ($items as $item) {
                $producto = Producto::findOrFail($item['producto_id']);
                
                // Verificar stock
                if ($producto->stock < $item['cantidad']) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock insuficiente para el producto: {$producto->nombre}"
                    ]);
                }

                $precio = $producto->precio_oferta ?? $producto->precio;
                $itemTotal = $precio * $item['cantidad'];
                $subtotal += $itemTotal;

                $itemsValidados[] = [
                    'producto' => $producto,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $precio,
                    'subtotal' => $itemTotal,
                    'variacion_id' => $item['variacion_id'] ?? null
                ];
            }

            $igv = $subtotal * 0.18; // 18% IGV
            $total = $subtotal + $igv;

            // Obtener métodos de pago disponibles para este monto
            $metodosPago = MetodoPago::activo()
                ->disponibleEnPais($pais)
                ->soportaMoneda($moneda)
                ->disponibleParaMonto($total)
                ->ordenados()
                ->get()
                ->map(function ($metodo) use ($total) {
                    return [
                        'id' => $metodo->id,
                        'nombre' => $metodo->nombre,
                        'tipo' => $metodo->tipo,
                        'logo_url' => $metodo->logo_url,
                        'comision' => $metodo->calcularComision($total),
                        'tiempo_procesamiento' => $metodo->getTiempoProcesamiento(),
                        'permite_cuotas' => $metodo->permite_cuotas,
                        'cuotas_maximas' => $metodo->cuotas_maximas,
                        'descripcion' => $metodo->descripcion
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $itemsValidados,
                    'subtotal' => $subtotal,
                    'igv' => $igv,
                    'total' => $total,
                    'metodos_pago_disponibles' => $metodosPago,
                    'checkout_token' => uniqid('checkout_', true)
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de checkout inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al iniciar checkout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Validar datos personales
     */
    public function validarDatosPersonales(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'apellidos' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'telefono' => 'required|string|max:20',
                'documento_tipo' => 'required|string|in:DNI,CE,Pasaporte',
                'documento_numero' => 'required|string|max:20',
                'crear_cuenta' => 'boolean',
                'password' => 'required_if:crear_cuenta,true|min:8|confirmed'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Datos personales válidos',
                'data' => $validated
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos personales inválidos',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Validar dirección de envío
     */
    public function validarDireccionEnvio(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'direccion_id' => 'nullable|exists:direcciones,id',
                'nombre_contacto' => 'required|string|max:255',
                'telefono_contacto' => 'required|string|max:20',
                'direccion' => 'required|string|max:500',
                'referencia' => 'nullable|string|max:500',
                'distrito' => 'required|string|max:100',
                'provincia' => 'required|string|max:100',
                'departamento' => 'required|string|max:100',
                'codigo_postal' => 'nullable|string|max:10'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dirección de envío válida',
                'data' => $validated
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dirección de envío inválida',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Calcular costos de envío
     */
    public function calcularEnvio(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'distrito' => 'required|string',
                'peso_total' => 'required|numeric|min:0'
            ]);

            $distrito = $request->input('distrito');
            $pesoTotal = $request->input('peso_total');

            // Lógica de cálculo de envío basada en distrito y peso
            $metodosEnvio = [
                [
                    'id' => 1,
                    'nombre' => 'Envío Estándar',
                    'descripcion' => 'Entrega en 3-5 días hábiles',
                    'precio' => $this->calcularCostoEnvio($distrito, $pesoTotal, 'estandar'),
                    'tiempo_entrega' => '3-5 días hábiles'
                ],
                [
                    'id' => 2,
                    'nombre' => 'Envío Express',
                    'descripcion' => 'Entrega en 1-2 días hábiles',
                    'precio' => $this->calcularCostoEnvio($distrito, $pesoTotal, 'express'),
                    'tiempo_entrega' => '1-2 días hábiles'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'metodos_envio' => $metodosEnvio,
                    'envio_gratis_disponible' => $request->input('subtotal', 0) >= 100 // Envío gratis desde S/100
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos para cálculo de envío inválidos',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Aplicar cupón de descuento
     */
    public function aplicarCupon(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'codigo_cupon' => 'required|string',
                'subtotal' => 'required|numeric|min:0'
            ]);

            $codigoCupon = $request->input('codigo_cupon');
            $subtotal = $request->input('subtotal');

            $cupon = Cupon::where('codigo', $codigoCupon)
                ->where('activo', true)
                ->where('fecha_inicio', '<=', now())
                ->where('fecha_fin', '>=', now())
                ->first();

            if (!$cupon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cupón inválido o expirado'
                ], 422);
            }

            // Verificar uso del cupón
            if ($cupon->limite_uso && $cupon->veces_usado >= $cupon->limite_uso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cupón agotado'
                ], 422);
            }

            // Verificar monto mínimo
            if ($cupon->monto_minimo && $subtotal < $cupon->monto_minimo) {
                return response()->json([
                    'success' => false,
                    'message' => "Monto mínimo requerido: S/{$cupon->monto_minimo}"
                ], 422);
            }

            // Calcular descuento
            $descuento = 0;
            if ($cupon->tipo === 'porcentaje') {
                $descuento = ($subtotal * $cupon->valor) / 100;
                if ($cupon->descuento_maximo && $descuento > $cupon->descuento_maximo) {
                    $descuento = $cupon->descuento_maximo;
                }
            } else {
                $descuento = min($cupon->valor, $subtotal);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'cupon' => $cupon,
                    'descuento' => $descuento,
                    'nuevo_subtotal' => $subtotal - $descuento
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos del cupón inválidos',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Procesar pago y crear pedido
     */
    public function procesarPedido(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'datos_personales' => 'required|array',
                'datos_personales.nombre' => 'required|string|max:255',
                'datos_personales.apellidos' => 'required|string|max:255',
                'datos_personales.email' => 'required|email|max:255',
                'datos_personales.telefono' => 'required|string|max:20',
                'datos_personales.documento_tipo' => 'required|string|in:DNI,CE,Pasaporte',
                'datos_personales.documento_numero' => 'required|string|max:20',
                'direccion_envio' => 'required|array',
                'metodo_envio' => 'required|array',
                'metodo_pago_id' => 'required|exists:metodos_pago,id',
                'items' => 'required|array|min:1',
                'items.*.producto_id' => 'required|integer|exists:productos,id',
                'items.*.cantidad' => 'required|integer|min:1',
                'items.*.precio_unitario' => 'required|numeric|min:0',
                'items.*.subtotal' => 'required|numeric|min:0',
                'subtotal' => 'required|numeric|min:0',
                'descuento' => 'nullable|numeric|min:0',
                'costo_envio' => 'required|numeric|min:0',
                'igv' => 'required|numeric|min:0',
                'total' => 'required|numeric|min:0',
                'cupon_codigo' => 'nullable|string',
                'pais' => 'nullable|string|size:2',
                'moneda' => 'nullable|string|size:3'
            ]);

            // Validar método de pago
            $metodoPago = MetodoPago::findOrFail($validated['metodo_pago_id']);
            $pais = $validated['pais'] ?? 'PE';
            $moneda = $validated['moneda'] ?? 'PEN';
            $total = $validated['total'];

            // Validaciones del método de pago
            if (!$metodoPago->activo) {
                throw ValidationException::withMessages([
                    'metodo_pago_id' => 'El método de pago seleccionado no está disponible'
                ]);
            }

            if (!$metodoPago->estaDisponibleParaMonto($total)) {
                throw ValidationException::withMessages([
                    'total' => 'El monto no es válido para el método de pago seleccionado'
                ]);
            }

            if (!$metodoPago->estaDisponibleEnPais($pais)) {
                throw ValidationException::withMessages([
                    'metodo_pago_id' => 'El método de pago no está disponible en tu país'
                ]);
            }

            if ($metodoPago->moneda_soportada !== $moneda) {
                throw ValidationException::withMessages([
                    'moneda' => 'La moneda no es compatible con el método de pago seleccionado'
                ]);
            }

            // Detectar usuario autenticado (puede ser null para invitados)
            $userId = null;
            
            // Intentar obtener el usuario autenticado de diferentes formas
            if (Auth::check()) {
                $userId = Auth::id();
                Log::info('Usuario autenticado detectado por Auth::check()', ['user_id' => $userId]);
            } elseif ($request->user()) {
                $userId = $request->user()->id;
                Log::info('Usuario autenticado detectado por request->user()', ['user_id' => $userId]);
            }
            
            // Si no hay usuario autenticado, buscar por email en los datos personales
            if (!$userId) {
                $email = $validated['datos_personales']['email'];
                $usuario = User::where('email', $email)->first();
                if ($usuario) {
                    $userId = $usuario->id;
                    Log::info('Usuario encontrado por email', ['user_id' => $userId, 'email' => $email]);
                } else {
                    Log::info('Procesando pedido como invitado', ['email' => $email]);
                }
            }

            Log::info('Procesando pedido - Info de usuario', [
                'user_id_final' => $userId,
                'es_invitado' => is_null($userId),
                'email_cliente' => $validated['datos_personales']['email'],
                'metodo_pago_id' => $metodoPago->id,
                'metodo_pago_nombre' => $metodoPago->nombre
            ]);
            
            // Validar stock antes de crear el pedido
            foreach ($validated['items'] as $item) {
                $producto = Producto::findOrFail($item['producto_id']);
                if ($producto->stock < $item['cantidad']) {
                    DB::rollBack(); // Asegurar rollback en caso de error
                    return response()->json([
                        'success' => false,
                        'message' => "Stock insuficiente para el producto: {$producto->nombre}. Stock disponible: {$producto->stock}"
                    ], 422);
                }
                
                // Validar variación si existe
                if (isset($item['variacion_id'])) {
                    try {
                        $variacion = \App\Models\VariacionProducto::findOrFail($item['variacion_id']);
                        if ($variacion->producto_id != $item['producto_id']) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "La variación no pertenece al producto especificado"
                            ], 422);
                        }
                    } catch (Exception $e) {
                        DB::rollBack();
                        Log::error('Error al validar variación de producto', [
                            'variacion_id' => $item['variacion_id'],
                            'producto_id' => $item['producto_id'],
                            'error' => $e->getMessage()
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => "Variación de producto no válida"
                        ], 422);
                    }
                }
            }

            // Generar número de pedido único
            $numeroPedido = 'PED-' . date('Ymd') . '-' . str_pad((string)(Pedido::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);

            // Mapear tipo de método de pago a enum de la tabla pedidos
            $tipoPagoEnum = $this->mapearTipoPago($metodoPago->tipo);

            // Crear el pedido
            $pedido = Pedido::create([
                'user_id' => $userId, // Puede ser null para invitados
                'numero_pedido' => $numeroPedido,
                'total' => $validated['total'],
                'subtotal' => $validated['subtotal'],
                'descuento' => $validated['descuento'] ?? 0,
                'costo_envio' => $validated['costo_envio'],
                'igv' => $validated['igv'],
                'estado' => 'pendiente',
                'metodo_pago_id' => $metodoPago->id,
                'tipo_pago' => $tipoPagoEnum, // Usar el valor mapeado del enum
                'tipo_entrega' => 'delivery', // Valor por defecto para tipo_entrega
                'descuento_total' => $validated['descuento'] ?? 0,
                'datos_envio' => $validated['direccion_envio'],
                'metodo_envio' => $validated['metodo_envio'],
                'datos_cliente' => $validated['datos_personales'],
                'cupon_codigo' => $validated['cupon_codigo'] ?? null,
                'observaciones' => 'Pedido desde checkout web - ' . $validated['datos_personales']['email'] . ' - Método: ' . $metodoPago->nombre,
                'moneda' => $moneda,
                'canal_venta' => 'web',
            ]);

            // Crear los detalles del pedido
            foreach ($validated['items'] as $item) {
                DetallePedido::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $item['producto_id'],
                    'variacion_id' => $item['variacion_id'] ?? null,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $item['subtotal']
                ]);

                // Actualizar stock
                $producto = Producto::find($item['producto_id']);
                if ($producto) {
                    $producto->decrement('stock', $item['cantidad']);
                }
            }

            // Calcular comisión automáticamente
            try {
                $comision = $metodoPago->calcularComision($validated['total']);
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Error al calcular comisión del método de pago', [
                    'metodo_pago_id' => $metodoPago->id,
                    'total' => $validated['total'],
                    'error' => $e->getMessage()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error al calcular comisión del método de pago'
                ], 500);
            }

            // Crear registro de pago
            $pago = Pago::create([
                'pedido_id' => $pedido->id,
                'metodo_pago_id' => $metodoPago->id,
                'monto' => $validated['total'],
                'comision' => $comision,
                'fecha_pago' => now(),
                'estado' => 'pendiente',
                'metodo' => $metodoPago->tipo, // Mantener compatibilidad
                'referencia' => 'REF-' . strtoupper(uniqid()),
                'moneda' => $moneda,
                'observaciones' => 'Pago generado desde checkout - Método: ' . $metodoPago->nombre
            ]);

            // NUEVA FUNCIONALIDAD: Crear o actualizar cliente
            $cliente = $this->crearOActualizarCliente($validated, $userId);

            // Si se creó un cliente y el pedido no tenía user_id asignado, actualizar el pedido
            if ($cliente && !$pedido->user_id && $cliente->user_id) {
                $pedido->update(['user_id' => $cliente->user_id]);
                Log::info('Pedido actualizado con user_id del cliente', [
                    'pedido_id' => $pedido->id,
                    'user_id' => $cliente->user_id
                ]);
            }

            // Actualizar uso del cupón si existe
            if ($validated['cupon_codigo'] ?? null) {
                $cupon = Cupon::where('codigo', $validated['cupon_codigo'])->first();
                if ($cupon) {
                    $cupon->increment('veces_usado');
                }
            }

            DB::commit();

            Log::info('Pedido creado exitosamente', [
                'pedido_id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'user_id' => $userId,
                'cliente_id' => $cliente ? $cliente->id : null,
                'metodo_pago' => $metodoPago->nombre,
                'comision_calculada' => $comision,
                'es_invitado' => is_null($userId),
                'total' => $pedido->total,
                'email_cliente' => $validated['datos_personales']['email']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pedido creado exitosamente',
                'data' => [
                    'pedido' => $pedido->load('metodoPago'),
                    'pago' => $pago->load('metodoPago'),
                    'cliente' => $cliente,
                    'metodo_pago' => $metodoPago,
                    'comision_aplicada' => $comision,
                    'instrucciones_pago' => $metodoPago->instrucciones
                ]
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            Log::error('Error de validación al procesar pedido: ', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Datos del pedido inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al procesar pedido: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear o actualizar cliente basado en los datos del checkout
     */
    private function crearOActualizarCliente(array $validated, ?int $userId): ?Cliente
    {
        try {
            $datosPersonales = $validated['datos_personales'];
            $direccionEnvio = $validated['direccion_envio'];
            
            // Limpiar el número de documento (remover caracteres no numéricos para DNI)
            $numeroDocumento = preg_replace('/[^0-9A-Za-z]/', '', $datosPersonales['documento_numero']);
            
            // Buscar cliente existente por DNI o email
            $cliente = null;
            
            // Primero buscar por DNI
            if ($datosPersonales['documento_tipo'] === 'DNI') {
                $cliente = Cliente::where('dni', $numeroDocumento)->first();
            }
            
            // Si no se encontró por DNI y hay un usuario, buscar por user_id
            if (!$cliente && $userId) {
                $cliente = Cliente::where('user_id', $userId)->first();
            }
            
            // Si no se encontró por DNI ni user_id, buscar por email del usuario
            if (!$cliente) {
                $usuario = User::where('email', $datosPersonales['email'])->first();
                if ($usuario) {
                    $cliente = Cliente::where('user_id', $usuario->id)->first();
                    // Actualizar userId si se encontró el usuario
                    if (!$userId) {
                        $userId = $usuario->id;
                    }
                }
            }

            // Preparar los datos del cliente
            $datosCliente = [
                'user_id' => $userId,
                'dni' => $numeroDocumento,
                'telefono' => preg_replace('/[^0-9+]/', '', $datosPersonales['telefono']),
                'direccion' => $direccionEnvio['direccion'] ?? '',
                'nombre_completo' => $datosPersonales['nombre'],
                'apellidos' => $datosPersonales['apellidos'],
                'estado' => 'activo',
                'verificado' => false,
                'limite_credito' => 0,
                'metadata' => [
                    'fuente_registro' => 'checkout_web',
                    'ip_registro' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'documento_tipo_checkout' => $datosPersonales['documento_tipo'],
                    'direccion_envio_inicial' => $direccionEnvio,
                    'fecha_primer_pedido' => now()->format('Y-m-d H:i:s')
                ]
            ];

            if ($cliente) {
                // Actualizar cliente existente solo con datos importantes que pueden haber cambiado
                $datosActualizar = [
                    'telefono' => $datosCliente['telefono'],
                    'direccion' => $datosCliente['direccion'],
                ];
                
                // Solo actualizar nombre_completo y apellidos si están vacíos
                if (empty($cliente->nombre_completo)) {
                    $datosActualizar['nombre_completo'] = $datosCliente['nombre_completo'];
                }
                if (empty($cliente->apellidos)) {
                    $datosActualizar['apellidos'] = $datosCliente['apellidos'];
                }
                
                // Actualizar metadata agregando la info del nuevo pedido
                $metadataActual = $cliente->metadata ?? [];
                $metadataActual['ultimo_pedido_checkout'] = now()->format('Y-m-d H:i:s');
                $metadataActual['total_pedidos_checkout'] = ($metadataActual['total_pedidos_checkout'] ?? 0) + 1;
                $datosActualizar['metadata'] = $metadataActual;

                $cliente->update($datosActualizar);
                
                Log::info('Cliente actualizado desde checkout', [
                    'cliente_id' => $cliente->id,
                    'dni' => $cliente->dni,
                    'email' => $datosPersonales['email'],
                    'datos_actualizados' => array_keys($datosActualizar)
                ]);
            } else {
                // Crear nuevo cliente
                $cliente = Cliente::create($datosCliente);
                
                Log::info('Cliente creado desde checkout', [
                    'cliente_id' => $cliente->id,
                    'dni' => $cliente->dni,
                    'email' => $datosPersonales['email'],
                    'user_id' => $userId,
                    'es_nuevo_usuario' => is_null($userId)
                ]);
            }

            return $cliente;

        } catch (Exception $e) {
            Log::error('Error al crear/actualizar cliente desde checkout: ' . $e->getMessage(), [
                'datos_personales' => $datosPersonales ?? null,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            
            // No lanzar excepción para no romper el flujo del pedido
            // El pedido puede continuar sin cliente en la tabla
            return null;
        }
    }

    /**
     * Calcular costo de envío
     */
    private function calcularCostoEnvio(string $distrito, float $peso, string $tipo): float
    {
        // Lógica básica de cálculo de envío
        $baseCosto = 10.0; // Costo base
        $costoPorKg = 2.0; // Costo por kg adicional
        
        $costo = $baseCosto + ($peso * $costoPorKg);
        
        // Multiplicador por tipo de envío
        if ($tipo === 'express') {
            $costo *= 1.5;
        }
        
        // Ajuste por distrito (simplificado)
        $distritosCentricos = ['Miraflores', 'San Isidro', 'Surco', 'Barranco'];
        if (!in_array($distrito, $distritosCentricos)) {
            $costo += 5.0;
        }
        
        return round($costo, 2);
    }

    /**
     * Obtener resumen del checkout
     */
    public function obtenerResumen(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.producto_id' => 'required|exists:productos,id',
                'items.*.cantidad' => 'required|integer|min:1',
                'cupon_codigo' => 'nullable|string',
                'distrito' => 'nullable|string',
                'metodo_envio' => 'nullable|string'
            ]);

            $items = $request->input('items');
            $cuponCodigo = $request->input('cupon_codigo');
            $distrito = $request->input('distrito', 'Lima');
            $metodoEnvio = $request->input('metodo_envio', 'normal');

            // Calcular subtotal
            $subtotal = 0;
            $pesoTotal = 0;
            $itemsDetalle = [];

            foreach ($items as $item) {
                $producto = Producto::find($item['producto_id']);
                if (!$producto) continue;

                $precio = $producto->precio_oferta ?? $producto->precio;
                $subtotalItem = $precio * $item['cantidad'];
                $subtotal += $subtotalItem;
                $pesoTotal += ($producto->peso ?? 0.5) * $item['cantidad'];

                $itemsDetalle[] = [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'precio_unitario' => $precio,
                    'cantidad' => $item['cantidad'],
                    'subtotal' => $subtotalItem
                ];
            }

            // Aplicar cupón si existe
            $descuento = 0;
            $cuponAplicado = null;
            if ($cuponCodigo) {
                $cupon = Cupon::where('codigo', $cuponCodigo)
                    ->where('activo', true)
                    ->where('fecha_inicio', '<=', now())
                    ->where('fecha_fin', '>=', now())
                    ->first();

                if ($cupon && $subtotal >= ($cupon->monto_minimo ?? 0)) {
                    if ($cupon->tipo === 'porcentaje') {
                        $descuento = ($subtotal * $cupon->valor) / 100;
                        if ($cupon->descuento_maximo) {
                            $descuento = min($descuento, $cupon->descuento_maximo);
                        }
                    } else {
                        $descuento = $cupon->valor;
                    }
                    $cuponAplicado = $cupon;
                }
            }

            // Calcular envío
            $costoEnvio = $this->calcularCostoEnvio($distrito, $pesoTotal, $metodoEnvio);

            // Calcular IGV
            $subtotalConDescuento = $subtotal - $descuento;
            $igv = ($subtotalConDescuento + $costoEnvio) * 0.18;

            // Total
            $total = $subtotalConDescuento + $costoEnvio + $igv;

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $itemsDetalle,
                    'items_count' => count($itemsDetalle),
                    'peso_total' => $pesoTotal,
                    'subtotal' => round($subtotal, 2),
                    'descuento' => round($descuento, 2),
                    'costo_envio' => round($costoEnvio, 2),
                    'igv' => round($igv, 2),
                    'total' => round($total, 2),
                    'cupon_aplicado' => $cuponAplicado,
                    'metodo_envio' => $metodoEnvio,
                    'distrito' => $distrito
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al obtener resumen del checkout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener configuración del checkout
     */
    public function obtenerConfiguracion(Request $request): JsonResponse
    {
        try {
            $configuracion = [
                'permitir_invitados' => true,
                'validar_stock_tiempo_real' => true,
                'tiempo_sesion_checkout' => 30, // minutos
                'metodos_pago_disponibles' => [
                    'tarjeta_credito',
                    'tarjeta_debito',
                    'yape',
                    'plin',
                    'transferencia_bancaria',
                    'contra_entrega'
                ],
                'envio_gratis_monto_minimo' => 150.00,
                'calcular_igv' => true,
                'porcentaje_igv' => 18.0,
                'moneda_defecto' => 'PEN',
                'pais_defecto' => 'PE',
                'tipos_documento' => [
                    'DNI' => 'Documento Nacional de Identidad',
                    'CE' => 'Carné de Extranjería',
                    'Pasaporte' => 'Pasaporte'
                ],
                'metodos_envio' => [
                    'normal' => [
                        'nombre' => 'Envío Normal',
                        'tiempo' => '3-5 días hábiles',
                        'descripcion' => 'Envío estándar a domicilio'
                    ],
                    'express' => [
                        'nombre' => 'Envío Express',
                        'tiempo' => '1-2 días hábiles',
                        'descripcion' => 'Envío rápido con seguimiento'
                    ]
                ],
                'validaciones' => [
                    'telefono_requerido' => true,
                    'direccion_referencia_requerida' => false,
                    'validar_codigo_postal' => false,
                    'verificar_identidad' => false
                ],
                'limites' => [
                    'items_maximo_carrito' => 50,
                    'cantidad_maxima_item' => 10,
                    'monto_maximo_pedido' => 10000.00,
                    'peso_maximo_envio' => 30.0 // kg
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $configuracion
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener configuración del checkout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Mapear tipo de método de pago a enum de la tabla pedidos
     */
    private function mapearTipoPago(string $tipo): string
    {
        // Mapear tipos de métodos de pago a valores del enum de la tabla pedidos
        // Enum: ['contado', 'credito', 'transferencia', 'tarjeta', 'yape', 'plin', 'paypal']
        $mapa = [
            'tarjeta_credito' => 'tarjeta',
            'tarjeta_debito' => 'tarjeta',
            'yape' => 'yape',
            'plin' => 'plin',
            'transferencia_bancaria' => 'transferencia',
            'transferencia' => 'transferencia',
            'contra_entrega' => 'contado',
            'efectivo' => 'contado',
            'billetera_digital' => 'contado',
            'paypal' => 'paypal',
            'credito' => 'credito',
        ];

        return $mapa[$tipo] ?? 'contado'; // Fallback a 'contado'
    }

    // ===== MÉTODOS DE IZIPAY =====

    /**
     * Generar formToken para Izipay
     */
    public function generarFormTokenIzipay(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'pedido_id' => 'required|exists:pedidos,id',
                'datos_personales' => 'required|array',
                'datos_personales.nombre' => 'required|string|max:255',
                'datos_personales.apellidos' => 'required|string|max:255',
                'datos_personales.email' => 'required|email|max:255',
                'datos_personales.telefono' => 'required|string|max:20',
                'datos_personales.documento_tipo' => 'required|string|in:DNI,CE,Pasaporte',
                'datos_personales.documento_numero' => 'required|string|max:20',
                'direccion_envio' => 'required|array',
                'direccion_envio.direccion' => 'required|string|max:500',
                'direccion_envio.distrito' => 'required|string|max:100',
                'direccion_envio.provincia' => 'required|string|max:100',
                'direccion_envio.departamento' => 'required|string|max:100',
                'direccion_envio.codigo_postal' => 'nullable|string|max:10'
            ]);

            // Obtener el pedido
            $pedido = Pedido::findOrFail($validated['pedido_id']);

            // Verificar que el pedido está en estado pendiente
            if ($pedido->estado !== 'pendiente') {
                return response()->json([
                    'success' => false,
                    'message' => 'El pedido no se encuentra en estado pendiente'
                ], 422);
            }

            // Preparar datos para Izipay siguiendo el formato de la documentación
            $datosVenta = [
                'amount' => (float) $pedido->total, // No multiplicar por 100 aquí, el servicio lo hace
                'currency' => $pedido->moneda ?? 'PEN',
                'orderId' => $pedido->numero_pedido,
                'customer' => [
                    'email' => $validated['datos_personales']['email'],
                    'firstName' => $validated['datos_personales']['nombre'],
                    'lastName' => $validated['datos_personales']['apellidos'],
                    'phoneNumber' => $validated['datos_personales']['telefono'],
                    'identityType' => $validated['datos_personales']['documento_tipo'],
                    'identityCode' => $validated['datos_personales']['documento_numero'],
                    'address' => $validated['direccion_envio']['direccion'],
                    'country' => 'PE',
                    'city' => $validated['direccion_envio']['distrito'],
                    'state' => $validated['direccion_envio']['departamento'],
                    'zipCode' => $validated['direccion_envio']['codigo_postal'] ?? '15001'
                ]
            ];

            // Generar formToken con Izipay
            $resultado = $this->izipayService->generarFormToken($datosVenta);

            // Actualizar el pedido con información de Izipay
            $pedido->update([
                'observaciones' => ($pedido->observaciones ?? '') . ' | FormToken generado para Izipay'
            ]);

            Log::info('FormToken de Izipay generado exitosamente', [
                'pedido_id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'email_cliente' => $validated['datos_personales']['email']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'formToken' => $resultado['formToken'],
                    'publicKey' => $resultado['publicKey'],
                    'pedido' => $pedido,
                    'endpoint_izipay' => config('services.izipay.endpoint')
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos para generar formToken',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al generar formToken de Izipay', [
                'error' => $e->getMessage(),
                'pedido_id' => $validated['pedido_id'] ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar formToken de Izipay: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar pago de Izipay
     */
    public function validarPagoIzipay(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'kr-answer' => 'required|string',
                'kr-hash' => 'required|string',
                'pedido_id' => 'nullable|exists:pedidos,id'
            ]);

            // Procesar respuesta de Izipay
            $resultado = $this->izipayService->procesarRespuestaPago($validated);

            // Si el pago es exitoso, actualizar el pedido
            if ($resultado['success']) {
                // Buscar el pedido por número de orden
                $pedido = null;
                if (isset($validated['pedido_id'])) {
                    $pedido = Pedido::find($validated['pedido_id']);
                } elseif ($resultado['order_id']) {
                    $pedido = Pedido::where('numero_pedido', $resultado['order_id'])->first();
                }

                if ($pedido) {
                    // Actualizar pedido como aprobado (pagado)
                    $pedido->update([
                        'estado' => 'aprobado', // Usar 'aprobado' en lugar de 'pagado'
                        'observaciones' => ($pedido->observaciones ?? '') . 
                            " | Pago confirmado por Izipay - UUID: {$resultado['transaction_uuid']}"
                    ]);

                    // Actualizar el registro de pago si existe
                    $pago = Pago::where('pedido_id', $pedido->id)->first();
                    if ($pago) {
                        $pago->update([
                            'estado' => 'pagado',
                            'referencia' => $resultado['transaction_uuid'],
                            'observaciones' => ($pago->observaciones ?? '') . 
                                ' | Confirmado por Izipay'
                        ]);
                    }

                    Log::info('Pago de Izipay confirmado exitosamente', [
                        'pedido_id' => $pedido->id,
                        'numero_pedido' => $pedido->numero_pedido,
                        'transaction_uuid' => $resultado['transaction_uuid'],
                        'amount' => $resultado['amount']
                    ]);
                } else {
                    Log::warning('Pedido no encontrado para confirmar pago de Izipay', [
                        'order_id' => $resultado['order_id'],
                        'pedido_id' => $validated['pedido_id'] ?? null
                    ]);
                }
            }

            return response()->json([
                'success' => $resultado['success'],
                'data' => [
                    'order_status' => $resultado['order_status'],
                    'transaction_uuid' => $resultado['transaction_uuid'],
                    'order_id' => $resultado['order_id'],
                    'amount' => $resultado['amount'],
                    'pedido' => $pedido ?? null
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al validar pago de Izipay', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al validar pago de Izipay: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar IPN de Izipay
     */
    public function procesarIPNIzipay(Request $request): Response
    {
        try {
            $datosIPN = $request->only(['kr-answer', 'kr-hash']);

            // Procesar IPN
            $resultado = $this->izipayService->procesarIPN($datosIPN);

            // Buscar y actualizar el pedido
            $pedido = Pedido::where('numero_pedido', $resultado['order_id'])->first();

            if ($pedido) {
                $estadoAnterior = $pedido->estado;

                // Actualizar estado del pedido basado en el estado de Izipay
                $nuevoEstado = $resultado['order_status'] === 'PAID' ? 'aprobado' : 'cancelado';
                
                $pedido->update([
                    'estado' => $nuevoEstado,
                    'observaciones' => ($pedido->observaciones ?? '') . 
                        " | IPN Izipay - Estado: {$resultado['order_status']} - UUID: {$resultado['transaction_uuid']}"
                ]);

                // Actualizar pago si existe
                $pago = Pago::where('pedido_id', $pedido->id)->first();
                if ($pago) {
                    $pago->update([
                        'estado' => $nuevoEstado === 'aprobado' ? 'pagado' : 'fallido',
                        'referencia' => $resultado['transaction_uuid'],
                        'observaciones' => ($pago->observaciones ?? '') . ' | IPN Izipay procesado'
                    ]);
                }

                Log::info('IPN de Izipay procesado exitosamente', [
                    'pedido_id' => $pedido->id,
                    'numero_pedido' => $pedido->numero_pedido,
                    'estado_anterior' => $estadoAnterior,
                    'nuevo_estado' => $nuevoEstado,
                    'order_status' => $resultado['order_status'],
                    'transaction_uuid' => $resultado['transaction_uuid']
                ]);

                // Respuesta requerida por Izipay
                return response('OK! OrderStatus is ' . $resultado['order_status'], 200)
                    ->header('Content-Type', 'text/plain');
            } else {
                Log::warning('IPN Izipay: Pedido no encontrado', [
                    'order_id' => $resultado['order_id'],
                    'order_status' => $resultado['order_status']
                ]);

                return response('Pedido no encontrado', 404)
                    ->header('Content-Type', 'text/plain');
            }

        } catch (Exception $e) {
            Log::error('Error al procesar IPN de Izipay', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response('Error al procesar IPN', 500)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Verificar configuración de Izipay
     */
    public function verificarConfiguracionIzipay(Request $request): JsonResponse
    {
        try {
            $configuracion = $this->izipayService->verificarConfiguracion();

            return response()->json([
                'success' => true,
                'data' => $configuracion
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al verificar configuración de Izipay: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar configuración de Izipay',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método de prueba para verificar credenciales de Izipay
     */
    public function testIzipayConfig(Request $request): JsonResponse
    {
        try {
            $config = [
                'IZIPAY_USERNAME' => config('services.izipay.username'),
                'IZIPAY_PASSWORD' => config('services.izipay.password') ? '***' : 'NO CONFIGURADO',
                'IZIPAY_PUBLIC_KEY' => config('services.izipay.public_key') ? '***' : 'NO CONFIGURADO',
                'IZIPAY_SHA256_KEY' => config('services.izipay.sha256_key') ? '***' : 'NO CONFIGURADO',
                'IZIPAY_API_URL' => config('services.izipay.api_url'),
                'configuracion_completa' => !empty(config('services.izipay.username')) && 
                                          !empty(config('services.izipay.password')) && 
                                          !empty(config('services.izipay.public_key')) && 
                                          !empty(config('services.izipay.sha256_key'))
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'Configuración de Izipay verificada',
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar configuración: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint de diagnóstico para verificar el estado del sistema
     */
    public function diagnostico(Request $request): JsonResponse
    {
        try {
            $diagnostico = [
                'timestamp' => now()->toISOString(),
                'servidor' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'timezone' => config('app.timezone'),
                    'debug_mode' => config('app.debug'),
                ],
                'base_datos' => [
                    'conexion_activa' => false,
                    'total_productos' => 0,
                    'total_metodos_pago' => 0,
                ],
                'configuracion_izipay' => [
                    'username_configurado' => !empty(config('services.izipay.username')),
                    'password_configurado' => !empty(config('services.izipay.password')),
                    'public_key_configurado' => !empty(config('services.izipay.public_key')),
                    'sha256_key_configurado' => !empty(config('services.izipay.sha256_key')),
                    'api_url' => config('services.izipay.api_url'),
                ],
                'ssl_environment' => [
                    'openssl_version' => OPENSSL_VERSION_TEXT,
                    'curl_version' => curl_version(),
                    'ca_bundle_paths' => $this->checkCABundlePaths(),
                    'is_ubuntu' => $this->isUbuntuSystem(),
                    'php_os_family' => PHP_OS_FAMILY,
                ],
                'permisos' => [
                    'storage_writable' => is_writable(storage_path()),
                    'logs_writable' => is_writable(storage_path('logs')),
                ],
                'memoria' => [
                    'uso_actual' => memory_get_usage(true),
                    'pico_memoria' => memory_get_peak_usage(true),
                    'limite_memoria' => ini_get('memory_limit'),
                ]
            ];

            // Verificar conexión a base de datos
            try {
                DB::connection()->getPdo();
                $diagnostico['base_datos']['conexion_activa'] = true;
                $diagnostico['base_datos']['total_productos'] = Producto::count();
                $diagnostico['base_datos']['total_metodos_pago'] = MetodoPago::count();
            } catch (Exception $e) {
                $diagnostico['base_datos']['error'] = $e->getMessage();
            }

            // Test de conectividad SSL a Izipay
            $diagnostico['izipay_connectivity'] = $this->testIzipayConnectivity();

            return response()->json([
                'success' => true,
                'data' => $diagnostico
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en diagnóstico: ' . $e->getMessage(),
                'error' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Verificar rutas de certificados CA
     */
    private function checkCABundlePaths(): array
    {
        $paths = [
            '/etc/ssl/certs/ca-certificates.crt', // Ubuntu/Debian
            '/etc/pki/tls/certs/ca-bundle.crt',   // CentOS/RHEL
            '/etc/ssl/ca-bundle.pem',             // OpenSUSE
            '/usr/local/share/certs/ca-root-nss.crt', // FreeBSD
            '/etc/ssl/cert.pem',                  // Alpine Linux
        ];

        $result = [];
        foreach ($paths as $path) {
            $result[$path] = [
                'exists' => file_exists($path),
                'readable' => file_exists($path) && is_readable($path),
                'size' => file_exists($path) ? filesize($path) : 0
            ];
        }

        return $result;
    }

    /**
     * Detectar si es sistema Ubuntu
     */
    private function isUbuntuSystem(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        $osRelease = '/etc/os-release';
        if (file_exists($osRelease)) {
            $content = file_get_contents($osRelease);
            return stripos($content, 'ubuntu') !== false;
        }

        return false;
    }

    /**
     * Test de conectividad SSL a Izipay
     */
    private function testIzipayConnectivity(): array
    {
        $tests = [];
        
        // Test 1: Conectividad básica
        $tests['basic_connectivity'] = $this->testBasicConnectivity();
        
        // Test 2: SSL con verificación completa
        $tests['ssl_full_verification'] = $this->testSSLFullVerification();
        
        // Test 3: SSL sin verificación (solo para debug)
        if (config('app.debug')) {
            $tests['ssl_no_verification'] = $this->testSSLNoVerification();
        }
        
        return $tests;
    }

    /**
     * Test de conectividad básica
     */
    private function testBasicConnectivity(): array
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.micuentaweb.pe');
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            return [
                'success' => $result !== false && $httpCode > 0,
                'http_code' => $httpCode,
                'error' => $error ?: null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test SSL con verificación completa
     */
    private function testSSLFullVerification(): array
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.micuentaweb.pe');
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            // Intentar usar CA bundle si existe
            $caBundlePaths = [
                '/etc/ssl/certs/ca-certificates.crt',
                '/etc/pki/tls/certs/ca-bundle.crt',
                '/etc/ssl/ca-bundle.pem'
            ];
            
            $caBundleUsed = null;
            foreach ($caBundlePaths as $path) {
                if (file_exists($path) && is_readable($path)) {
                    curl_setopt($ch, CURLOPT_CAINFO, $path);
                    $caBundleUsed = $path;
                    break;
                }
            }
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $sslInfo = curl_getinfo($ch);
            curl_close($ch);
            
            return [
                'success' => $result !== false && $httpCode > 0,
                'http_code' => $httpCode,
                'error' => $error ?: null,
                'ca_bundle_used' => $caBundleUsed,
                'ssl_verify_result' => $sslInfo['ssl_verify_result'] ?? null,
                'certinfo' => $sslInfo['certinfo'] ?? null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test SSL sin verificación (solo para debug)
     */
    private function testSSLNoVerification(): array
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.micuentaweb.pe');
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            return [
                'success' => $result !== false && $httpCode > 0,
                'http_code' => $httpCode,
                'error' => $error ?: null,
                'note' => 'SSL verification disabled - for debug only'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Simular validación de pago exitosa para pruebas
     */
    public function simularPagoExitoso(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'pedido_id' => 'required|exists:pedidos,id'
            ]);

            $pedido = Pedido::findOrFail($validated['pedido_id']);

            // Simular actualización del pedido como aprobado (pagado)
            $pedido->update([
                'estado' => 'aprobado', // Usar 'aprobado' en lugar de 'pagado'
                'observaciones' => ($pedido->observaciones ?? '') . 
                    ' | PAGO SIMULADO - UUID: TEST-' . substr(uniqid(), -8) // Acortar para evitar truncamiento
            ]);

            // Actualizar el registro de pago si existe
            $pago = Pago::where('pedido_id', $pedido->id)->first();
            if ($pago) {
                $pago->update([
                    'estado' => 'pagado', // Usar 'pagado' en lugar de 'completado'
                    'referencia' => 'TEST-' . substr(uniqid(), -8),
                    'observaciones' => ($pago->observaciones ?? '') . ' | Pago simulado'
                ]);
            }

            Log::info('Pago simulado exitosamente', [
                'pedido_id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_status' => 'PAID',
                    'transaction_uuid' => 'TEST-' . uniqid(),
                    'order_id' => $pedido->numero_pedido,
                    'amount' => $pedido->total,
                    'pedido' => $pedido->fresh(),
                    'message' => 'Pago simulado exitosamente'
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al simular pago', [
                'error' => $e->getMessage(),
                'pedido_id' => $validated['pedido_id'] ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al simular pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test de configuración de dominio para Izipay
     */
    public function testDomainConfig(Request $request): JsonResponse
    {
        try {
            $currentDomain = $request->getHost();
            $fullUrl = $request->getSchemeAndHttpHost();
            
            // Configuración actual de Izipay
            $izipayConfig = [
                'username' => config('services.izipay.username'),
                'api_url' => config('services.izipay.api_url'),
                'endpoint' => config('services.izipay.endpoint')
            ];
            
            // Headers de la petición actual
            $requestHeaders = [
                'host' => $request->header('host'),
                'origin' => $request->header('origin'),
                'referer' => $request->header('referer'),
                'user_agent' => $request->header('user-agent')
            ];
            
            // Información del dominio
            $domainInfo = [
                'current_domain' => $currentDomain,
                'full_url' => $fullUrl,
                'protocol' => $request->getScheme(),
                'is_https' => $request->isSecure(),
                'request_uri' => $request->getRequestUri()
            ];
            
            // Probable configuración necesaria en Izipay
            $requiredIzipayConfig = [
                'url_principal' => $fullUrl,
                'url_retorno_test' => $fullUrl . '/checkout/success',
                'url_retorno_produccion' => $fullUrl . '/checkout/success',
                'url_ipn' => $fullUrl . '/api/checkout/izipay/ipn',
                'dominio_autorizado' => $currentDomain
            ];
            
            // Test de headers para Izipay
            $mockIzipayHeaders = [
                'Origin' => $fullUrl,
                'Referer' => $fullUrl . '/checkout',
                'Host' => $currentDomain,
                'X-Forwarded-Host' => $currentDomain,
                'X-Forwarded-Proto' => $request->getScheme()
            ];
            
            // Verificar si el dominio actual coincide con algún patrón común
            $domainAnalysis = [
                'is_localhost' => in_array($currentDomain, ['localhost', '127.0.0.1']),
                'is_ip_address' => filter_var($currentDomain, FILTER_VALIDATE_IP) !== false,
                'has_ssl' => $request->isSecure(),
                'domain_parts' => explode('.', $currentDomain),
                'likely_production' => !in_array($currentDomain, ['localhost', '127.0.0.1']) && 
                                     !str_contains($currentDomain, 'test') && 
                                     !str_contains($currentDomain, 'dev')
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'domain_info' => $domainInfo,
                    'izipay_config' => $izipayConfig,
                    'request_headers' => $requestHeaders,
                    'domain_analysis' => $domainAnalysis,
                    'required_izipay_config' => $requiredIzipayConfig,
                    'mock_headers_for_izipay' => $mockIzipayHeaders,
                    'recommendations' => [
                        'action_needed' => 'Configurar dominio en Izipay Back Office',
                        'current_domain' => $currentDomain,
                        'configured_for' => 'senshi.pe (según imagen proporcionada)',
                        'status' => 'DOMAIN_MISMATCH_LIKELY_CAUSE',
                        'next_steps' => [
                            '1. Acceder al Back Office de Izipay',
                            '2. Cambiar URL principal a: ' . $fullUrl,
                            '3. Configurar URLs de retorno',
                            '4. Configurar URL de IPN',
                            '5. Guardar cambios y probar nuevamente'
                        ]
                    ]
                ]
            ], 200);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al analizar configuración de dominio: ' . $e->getMessage()
            ], 500);
        }
    }
} 