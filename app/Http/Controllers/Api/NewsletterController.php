<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Exception;

class NewsletterController extends Controller
{
    /**
     * Suscribirse al newsletter
     */
    public function suscribirse(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'nombre' => 'nullable|string|max:255',
                'intereses' => 'nullable|array',
                'intereses.*' => 'in:ofertas,nuevos_productos,tecnologia,hogar,moda,deportes',
                'acepta_politicas' => 'required|boolean|accepted'
            ]);

            // Verificar si ya está suscrito
            if ($this->yaEstaSuscrito($validated['email'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este email ya está suscrito a nuestro newsletter'
                ], 422);
            }

            // Simular guardado en base de datos
            $suscripcion = $this->crearSuscripcion($validated);

            // Enviar email de bienvenida
            $this->enviarEmailBienvenida($validated['email'], $validated['nombre'] ?? null);

            Log::info('Nueva suscripción al newsletter', [
                'email' => $validated['email'],
                'nombre' => $validated['nombre'] ?? 'No proporcionado',
                'intereses' => $validated['intereses'] ?? [],
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => '¡Te has suscrito exitosamente! Revisa tu email para confirmar la suscripción.',
                'data' => [
                    'id_suscripcion' => $suscripcion['id'],
                    'token_confirmacion' => $suscripcion['token']
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de suscripción inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al suscribir al newsletter: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Confirmar suscripción
     */
    public function confirmar(string $token): JsonResponse
    {
        try {
            // Simular verificación de token
            if (!$this->validarToken($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de confirmación inválido o expirado'
                ], 422);
            }

            // Simular activación de suscripción
            $this->activarSuscripcion($token);

            Log::info('Suscripción confirmada', [
                'token' => $token
            ]);

            return response()->json([
                'success' => true,
                'message' => '¡Suscripción confirmada exitosamente! Comenzarás a recibir nuestras novedades.'
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al confirmar suscripción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Desuscribirse del newsletter
     */
    public function desuscribirse(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'token' => 'nullable|string',
                'motivo' => 'nullable|in:muchos_emails,no_relevante,contenido_pobre,cambio_email,otro'
            ]);

            // Verificar si está suscrito
            if (!$this->yaEstaSuscrito($validated['email'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este email no está suscrito a nuestro newsletter'
                ], 422);
            }

            // Simular desactivación
            $this->desactivarSuscripcion($validated['email']);

            // Registrar motivo si se proporciona
            if ($validated['motivo'] ?? null) {
                Log::info('Motivo de desuscripción', [
                    'email' => $validated['email'],
                    'motivo' => $validated['motivo']
                ]);
            }

            Log::info('Usuario desuscrito del newsletter', [
                'email' => $validated['email'],
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Te has desuscrito exitosamente. Lamentamos verte partir.'
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos para desuscripción',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al desuscribir del newsletter: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener preferencias de suscripción
     */
    public function obtenerPreferencias(string $email): JsonResponse
    {
        try {
            if (!$this->yaEstaSuscrito($email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email no encontrado en nuestras suscripciones'
                ], 404);
            }

            $preferencias = $this->obtenerPreferenciasSuscriptor($email);

            return response()->json([
                'success' => true,
                'data' => $preferencias
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener preferencias: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualizar preferencias de suscripción
     */
    public function actualizarPreferencias(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'nombre' => 'nullable|string|max:255',
                'frecuencia' => 'required|in:diaria,semanal,quincenal,mensual',
                'intereses' => 'nullable|array',
                'intereses.*' => 'in:ofertas,nuevos_productos,tecnologia,hogar,moda,deportes',
                'formato' => 'required|in:html,texto'
            ]);

            if (!$this->yaEstaSuscrito($validated['email'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email no encontrado en nuestras suscripciones'
                ], 404);
            }

            // Simular actualización de preferencias
            $this->actualizarPreferenciasSuscriptor($validated);

            Log::info('Preferencias de newsletter actualizadas', [
                'email' => $validated['email'],
                'frecuencia' => $validated['frecuencia'],
                'intereses' => $validated['intereses'] ?? []
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Preferencias actualizadas exitosamente'
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos para actualizar preferencias',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al actualizar preferencias: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas del newsletter
     */
    public function estadisticas(): JsonResponse
    {
        try {
            $estadisticas = [
                'total_suscriptores' => 15742,
                'suscriptores_activos' => 14891,
                'tasa_apertura' => 68.5,
                'tasa_clicks' => 12.3,
                'crecimiento_mensual' => 8.7,
                'por_intereses' => [
                    'ofertas' => 12456,
                    'nuevos_productos' => 9876,
                    'tecnologia' => 7654,
                    'hogar' => 6543,
                    'moda' => 5432,
                    'deportes' => 4321
                ],
                'por_frecuencia' => [
                    'semanal' => 8945,
                    'quincenal' => 4567,
                    'mensual' => 1879,
                    'diaria' => 500
                ],
                'ultimas_campanas' => [
                    [
                        'id' => 1,
                        'titulo' => 'Ofertas de Enero 2024',
                        'fecha_envio' => '2024-01-15',
                        'tasa_apertura' => 72.1,
                        'tasa_clicks' => 15.6
                    ],
                    [
                        'id' => 2,
                        'titulo' => 'Nuevos Productos Tech',
                        'fecha_envio' => '2024-01-10',
                        'tasa_apertura' => 68.9,
                        'tasa_clicks' => 11.2
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $estadisticas
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener estadísticas del newsletter: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener tipos de intereses disponibles
     */
    public function tiposIntereses(): JsonResponse
    {
        try {
            $intereses = [
                [
                    'id' => 'ofertas',
                    'nombre' => 'Ofertas y Descuentos',
                    'descripcion' => 'Recibe las mejores ofertas y promociones exclusivas',
                    'icono' => 'tag'
                ],
                [
                    'id' => 'nuevos_productos',
                    'nombre' => 'Nuevos Productos',
                    'descripcion' => 'Sé el primero en conocer nuestros últimos productos',
                    'icono' => 'sparkles'
                ],
                [
                    'id' => 'tecnologia',
                    'nombre' => 'Tecnología',
                    'descripcion' => 'Lo último en gadgets y dispositivos tecnológicos',
                    'icono' => 'cpu-chip'
                ],
                [
                    'id' => 'hogar',
                    'nombre' => 'Hogar y Decoración',
                    'descripcion' => 'Productos para hacer tu hogar más cómodo y hermoso',
                    'icono' => 'home'
                ],
                [
                    'id' => 'moda',
                    'nombre' => 'Moda y Estilo',
                    'descripcion' => 'Tendencias de moda y accesorios de temporada',
                    'icono' => 'shopping-bag'
                ],
                [
                    'id' => 'deportes',
                    'nombre' => 'Deportes y Fitness',
                    'descripcion' => 'Equipamiento deportivo y productos para mantenerte activo',
                    'icono' => 'heart'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $intereses
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener tipos de intereses: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Verificar si un email ya está suscrito
     */
    private function yaEstaSuscrito(string $email): bool
    {
        // En una implementación real, esto consultaría la base de datos
        // Por ahora simulamos algunos emails ya suscritos
        $emailsSuscritos = [
            'test@example.com',
            'usuario@gmail.com',
            'cliente@hotmail.com'
        ];
        
        return in_array($email, $emailsSuscritos);
    }

    /**
     * Crear nueva suscripción
     */
    private function crearSuscripcion(array $datos): array
    {
        // En una implementación real, esto insertaría en la base de datos
        return [
            'id' => uniqid('sub_'),
            'email' => $datos['email'],
            'nombre' => $datos['nombre'] ?? null,
            'intereses' => $datos['intereses'] ?? [],
            'activo' => false, // Se activa al confirmar
            'token' => bin2hex(random_bytes(32)),
            'fecha_suscripcion' => now()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Validar token de confirmación
     */
    private function validarToken(string $token): bool
    {
        // En una implementación real, esto consultaría la base de datos
        // Por ahora simulamos que tokens de 64 caracteres son válidos
        return strlen($token) === 64 && ctype_xdigit($token);
    }

    /**
     * Activar suscripción
     */
    private function activarSuscripcion(string $token): void
    {
        // En una implementación real, esto actualizaría la base de datos
        Log::info('Suscripción activada simulada', ['token' => $token]);
    }

    /**
     * Desactivar suscripción
     */
    private function desactivarSuscripcion(string $email): void
    {
        // En una implementación real, esto actualizaría la base de datos
        Log::info('Suscripción desactivada simulada', ['email' => $email]);
    }

    /**
     * Obtener preferencias del suscriptor
     */
    private function obtenerPreferenciasSuscriptor(string $email): array
    {
        // En una implementación real, esto consultaría la base de datos
        return [
            'email' => $email,
            'nombre' => 'Usuario Ejemplo',
            'frecuencia' => 'semanal',
            'intereses' => ['ofertas', 'tecnologia', 'hogar'],
            'formato' => 'html',
            'activo' => true,
            'fecha_suscripcion' => '2024-01-01'
        ];
    }

    /**
     * Actualizar preferencias del suscriptor
     */
    private function actualizarPreferenciasSuscriptor(array $datos): void
    {
        // En una implementación real, esto actualizaría la base de datos
        Log::info('Preferencias actualizadas simuladas', $datos);
    }

    /**
     * Enviar email de bienvenida
     */
    private function enviarEmailBienvenida(string $email, ?string $nombre): void
    {
        // En una implementación real, esto enviaría el email
        // Mail::send('emails.newsletter.bienvenida', compact('nombre'), function ($message) use ($email) {
        //     $message->to($email)->subject('¡Bienvenido a nuestro Newsletter!');
        // });

        Log::info('Email de bienvenida enviado (simulado)', [
            'email' => $email,
            'nombre' => $nombre
        ]);
    }
} 