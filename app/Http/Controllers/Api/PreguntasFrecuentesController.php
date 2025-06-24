<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class PreguntasFrecuentesController extends Controller
{
    /**
     * Obtener todas las preguntas frecuentes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $preguntas = $this->obtenerPreguntasFrecuentes();

            // Filtrar por categoría si se especifica
            if ($request->has('categoria')) {
                $categoria = $request->query('categoria');
                $preguntas = array_filter($preguntas, fn($pregunta) => $pregunta['categoria'] === $categoria);
            }

            // Búsqueda por texto
            if ($request->has('buscar')) {
                $termino = strtolower($request->query('buscar'));
                $preguntas = array_filter($preguntas, function($pregunta) use ($termino) {
                    return str_contains(strtolower($pregunta['pregunta']), $termino) ||
                           str_contains(strtolower($pregunta['respuesta']), $termino);
                });
            }

            return response()->json([
                'success' => true,
                'data' => array_values($preguntas)
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener preguntas frecuentes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener pregunta frecuente específica
     */
    public function show(int $id): JsonResponse
    {
        try {
            $preguntas = $this->obtenerPreguntasFrecuentes();
            $pregunta = array_values(array_filter($preguntas, fn($p) => $p['id'] === $id));

            if (empty($pregunta)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pregunta frecuente no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $pregunta[0]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener pregunta frecuente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener categorías de preguntas frecuentes
     */
    public function categorias(): JsonResponse
    {
        try {
            $categorias = [
                [
                    'id' => 'pedidos',
                    'nombre' => 'Pedidos y Compras',
                    'descripcion' => 'Todo sobre el proceso de compra y seguimiento de pedidos',
                    'icono' => 'shopping-bag',
                    'orden' => 1
                ],
                [
                    'id' => 'envios',
                    'nombre' => 'Envíos y Entregas',
                    'descripcion' => 'Información sobre métodos de envío, tiempos y costos',
                    'icono' => 'truck',
                    'orden' => 2
                ],
                [
                    'id' => 'pagos',
                    'nombre' => 'Pagos y Facturación',
                    'descripcion' => 'Métodos de pago, facturación y problemas de cobro',
                    'icono' => 'credit-card',
                    'orden' => 3
                ],
                [
                    'id' => 'productos',
                    'nombre' => 'Productos',
                    'descripcion' => 'Información sobre productos, garantías y devoluciones',
                    'icono' => 'box',
                    'orden' => 4
                ],
                [
                    'id' => 'cuenta',
                    'nombre' => 'Mi Cuenta',
                    'descripcion' => 'Gestión de cuenta de usuario, contraseñas y datos personales',
                    'icono' => 'user',
                    'orden' => 5
                ],
                [
                    'id' => 'soporte',
                    'nombre' => 'Soporte Técnico',
                    'descripcion' => 'Ayuda técnica y resolución de problemas',
                    'icono' => 'tools',
                    'orden' => 6
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $categorias
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener categorías FAQ: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener preguntas por categoría
     */
    public function porCategoria(string $categoria): JsonResponse
    {
        try {
            $preguntas = $this->obtenerPreguntasFrecuentes();
            $preguntasCategoria = array_filter($preguntas, fn($pregunta) => $pregunta['categoria'] === $categoria);

            if (empty($preguntasCategoria)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Categoría no encontrada o sin preguntas'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => array_values($preguntasCategoria)
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener preguntas por categoría: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Búsqueda de preguntas frecuentes
     */
    public function buscar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2|max:255'
            ]);

            $termino = strtolower($request->query('q'));
            $preguntas = $this->obtenerPreguntasFrecuentes();

            $resultados = array_filter($preguntas, function($pregunta) use ($termino) {
                return str_contains(strtolower($pregunta['pregunta']), $termino) ||
                       str_contains(strtolower($pregunta['respuesta']), $termino) ||
                       str_contains(strtolower($pregunta['palabras_clave']), $termino);
            });

            // Ordenar por relevancia (coincidencias en el título tienen mayor peso)
            usort($resultados, function($a, $b) use ($termino) {
                $scoreA = str_contains(strtolower($a['pregunta']), $termino) ? 2 : 1;
                $scoreB = str_contains(strtolower($b['pregunta']), $termino) ? 2 : 1;
                return $scoreB - $scoreA;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'resultados' => array_values($resultados),
                    'total' => count($resultados),
                    'termino_busqueda' => $request->query('q')
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al buscar preguntas frecuentes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Marcar pregunta como útil
     */
    public function marcarUtil(int $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'util' => 'required|boolean'
            ]);

            // En una implementación real, esto se guardaría en base de datos
            Log::info('Pregunta marcada como útil', [
                'pregunta_id' => $id,
                'util' => $request->input('util'),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gracias por tu feedback'
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al marcar pregunta como útil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Sugerir nueva pregunta
     */
    public function sugerirPregunta(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'pregunta' => 'required|string|max:500',
                'email' => 'nullable|email|max:255',
                'categoria' => 'required|in:pedidos,envios,pagos,productos,cuenta,soporte'
            ]);

            // En una implementación real, esto se guardaría en base de datos
            Log::info('Nueva pregunta sugerida', [
                'pregunta' => $validated['pregunta'],
                'categoria' => $validated['categoria'],
                'email' => $validated['email'] ?? 'anónimo',
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gracias por tu sugerencia. La revisaremos y la añadiremos si es relevante.',
                'data' => [
                    'ticket_id' => 'SUG-' . date('Y') . '-' . str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT)
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al sugerir pregunta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de FAQ
     */
    public function estadisticas(): JsonResponse
    {
        try {
            $preguntas = $this->obtenerPreguntasFrecuentes();
            $categorias = $this->obtenerContadorPorCategoria($preguntas);

            $estadisticas = [
                'total_preguntas' => count($preguntas),
                'por_categoria' => $categorias,
                'mas_populares' => array_slice($preguntas, 0, 5), // Simula las más populares
                'actualizacion' => '2024-01-15'
            ];

            return response()->json([
                'success' => true,
                'data' => $estadisticas
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas FAQ: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener todas las preguntas frecuentes (datos hardcodeados)
     */
    private function obtenerPreguntasFrecuentes(): array
    {
        return [
            // PEDIDOS Y COMPRAS
            [
                'id' => 1,
                'pregunta' => '¿Cómo realizo un pedido?',
                'respuesta' => 'Para realizar un pedido, simplemente navega por nuestro catálogo, agrega los productos deseados al carrito y sigue el proceso de checkout. Puedes comprar como invitado o crear una cuenta para un proceso más rápido.',
                'categoria' => 'pedidos',
                'popularidad' => 95,
                'palabras_clave' => 'realizar pedido comprar carrito checkout'
            ],
            [
                'id' => 2,
                'pregunta' => '¿Puedo modificar o cancelar mi pedido?',
                'respuesta' => 'Puedes modificar o cancelar tu pedido solo si aún no ha sido procesado. Contáctanos inmediatamente después de realizar el pedido para hacer cambios.',
                'categoria' => 'pedidos',
                'popularidad' => 87,
                'palabras_clave' => 'modificar cancelar pedido cambios'
            ],
            [
                'id' => 3,
                'pregunta' => '¿Cómo puedo hacer seguimiento a mi pedido?',
                'respuesta' => 'Una vez confirmado tu pedido, recibirás un número de seguimiento por email. Puedes usar este número en la sección "Seguir mi pedido" o en tu cuenta de usuario.',
                'categoria' => 'pedidos',
                'popularidad' => 92,
                'palabras_clave' => 'seguimiento tracking rastrear pedido'
            ],

            // ENVÍOS Y ENTREGAS
            [
                'id' => 4,
                'pregunta' => '¿Cuáles son los métodos de envío disponibles?',
                'respuesta' => 'Ofrecemos envío estándar (3-5 días), envío express (1-2 días), same day (mismo día en Lima) y recojo en tienda. Los costos varían según la ubicación y el peso del pedido.',
                'categoria' => 'envios',
                'popularidad' => 90,
                'palabras_clave' => 'métodos envío delivery express estándar'
            ],
            [
                'id' => 5,
                'pregunta' => '¿Cuánto cuesta el envío?',
                'respuesta' => 'El costo de envío varía según el método seleccionado y la ubicación. Envío estándar desde S/10, express desde S/15. Envío gratis en pedidos mayores a S/150.',
                'categoria' => 'envios',
                'popularidad' => 88,
                'palabras_clave' => 'costo envío precio delivery gratis'
            ],
            [
                'id' => 6,
                'pregunta' => '¿Realizan entregas los fines de semana?',
                'respuesta' => 'Sí, realizamos entregas de lunes a sábado. Los domingos no hay servicio de entrega, excepto para el servicio same day que está disponible de lunes a sábado.',
                'categoria' => 'envios',
                'popularidad' => 75,
                'palabras_clave' => 'entregas fines semana domingo sábado'
            ],

            // PAGOS Y FACTURACIÓN
            [
                'id' => 7,
                'pregunta' => '¿Qué métodos de pago aceptan?',
                'respuesta' => 'Aceptamos tarjetas de crédito/débito (Visa, Mastercard, Amex), Yape, Plin, transferencia bancaria, PagoEfectivo, pago contra entrega y PayPal.',
                'categoria' => 'pagos',
                'popularidad' => 94,
                'palabras_clave' => 'métodos pago tarjeta yape plin paypal'
            ],
            [
                'id' => 8,
                'pregunta' => '¿Es seguro pagar con tarjeta en línea?',
                'respuesta' => 'Sí, utilizamos encriptación SSL de 256 bits y cumplimos con los estándares PCI DSS. Tus datos están completamente seguros y no almacenamos información de tarjetas.',
                'categoria' => 'pagos',
                'popularidad' => 82,
                'palabras_clave' => 'seguridad pago tarjeta ssl encriptación'
            ],
            [
                'id' => 9,
                'pregunta' => '¿Emiten factura o boleta?',
                'respuesta' => 'Sí, emitimos tanto facturas como boletas electrónicas. Puedes solicitar el tipo de comprobante que necesites durante el proceso de checkout.',
                'categoria' => 'pagos',
                'popularidad' => 78,
                'palabras_clave' => 'factura boleta comprobante electrónica'
            ],

            // PRODUCTOS
            [
                'id' => 10,
                'pregunta' => '¿Los productos tienen garantía?',
                'respuesta' => 'Todos nuestros productos cuentan con garantía del fabricante. El tiempo varía según el producto: electrónicos 12 meses, electrodomésticos 24 meses, ropa y accesorios 30 días.',
                'categoria' => 'productos',
                'popularidad' => 85,
                'palabras_clave' => 'garantía productos fabricante tiempo'
            ],
            [
                'id' => 11,
                'pregunta' => '¿Puedo devolver un producto?',
                'respuesta' => 'Sí, tienes 30 días para devolver productos en perfecto estado. El producto debe estar sin usar, con etiquetas originales y en su empaque original.',
                'categoria' => 'productos',
                'popularidad' => 89,
                'palabras_clave' => 'devolver devolución cambio producto'
            ],
            [
                'id' => 12,
                'pregunta' => '¿Cómo sé si un producto está disponible?',
                'respuesta' => 'La disponibilidad se muestra en tiempo real en cada producto. Si dice "En stock" está disponible. Si está agotado, puedes suscribirte para recibir notificación cuando esté disponible.',
                'categoria' => 'productos',
                'popularidad' => 80,
                'palabras_clave' => 'disponibilidad stock agotado notificación'
            ],

            // MI CUENTA
            [
                'id' => 13,
                'pregunta' => '¿Cómo creo una cuenta?',
                'respuesta' => 'Puedes crear una cuenta haciendo clic en "Registrarse" en la parte superior de la página, o durante el proceso de checkout. Solo necesitas tu email y una contraseña segura.',
                'categoria' => 'cuenta',
                'popularidad' => 70,
                'palabras_clave' => 'crear cuenta registrarse email contraseña'
            ],
            [
                'id' => 14,
                'pregunta' => '¿Olvidé mi contraseña, qué hago?',
                'respuesta' => 'En la página de login, haz clic en "¿Olvidaste tu contraseña?" e ingresa tu email. Te enviaremos un enlace para crear una nueva contraseña.',
                'categoria' => 'cuenta',
                'popularidad' => 77,
                'palabras_clave' => 'olvidé contraseña recuperar reset email'
            ],
            [
                'id' => 15,
                'pregunta' => '¿Puedo cambiar mis datos personales?',
                'respuesta' => 'Sí, puedes actualizar tus datos personales en cualquier momento desde la sección "Mi Cuenta" > "Configuración". Algunos cambios pueden requerir verificación adicional.',
                'categoria' => 'cuenta',
                'popularidad' => 65,
                'palabras_clave' => 'cambiar datos personales actualizar información'
            ],

            // SOPORTE TÉCNICO
            [
                'id' => 16,
                'pregunta' => '¿Cómo contacto al soporte técnico?',
                'respuesta' => 'Puedes contactarnos por WhatsApp (+51 987 654 321), email (soporte@mitienda.com), teléfono (01 234-5678) o através del formulario de contacto en la web.',
                'categoria' => 'soporte',
                'popularidad' => 72,
                'palabras_clave' => 'contacto soporte técnico whatsapp email teléfono'
            ],
            [
                'id' => 17,
                'pregunta' => '¿Qué horarios de atención tienen?',
                'respuesta' => 'Nuestro horario de atención es: Teléfono L-V 9:00-18:00, S 9:00-14:00. WhatsApp L-V 8:00-20:00, S 8:00-18:00, D 10:00-16:00. Email 24/7 con respuesta en 24-48 horas.',
                'categoria' => 'soporte',
                'popularidad' => 68,
                'palabras_clave' => 'horarios atención teléfono whatsapp email'
            ],
            [
                'id' => 18,
                'pregunta' => '¿Tienen servicio de instalación?',
                'respuesta' => 'Sí, ofrecemos servicio de instalación para electrodomésticos y equipos electrónicos. El costo varía según el producto y la complejidad de la instalación.',
                'categoria' => 'soporte',
                'popularidad' => 60,
                'palabras_clave' => 'instalación servicio electrodomésticos electrónicos'
            ]
        ];
    }

    /**
     * Obtener contador de preguntas por categoría
     */
    private function obtenerContadorPorCategoria(array $preguntas): array
    {
        $contador = [];
        foreach ($preguntas as $pregunta) {
            $categoria = $pregunta['categoria'];
            if (!isset($contador[$categoria])) {
                $contador[$categoria] = 0;
            }
            $contador[$categoria]++;
        }
        return $contador;
    }
} 