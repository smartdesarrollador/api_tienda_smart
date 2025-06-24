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

class ContactoController extends Controller
{
    /**
     * Enviar mensaje de contacto
     */
    public function enviarMensaje(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'apellidos' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'telefono' => 'nullable|string|max:20',
                'asunto' => 'required|string|max:255',
                'mensaje' => 'required|string|max:2000',
                'tipo_consulta' => 'required|in:general,ventas,soporte,reclamos,sugerencias',
                'acepta_politicas' => 'required|boolean|accepted'
            ]);

            // Simular envío de email (en producción usar Mail::send)
            $this->enviarEmailContacto($validated);

            // Registrar en logs para seguimiento
            Log::info('Mensaje de contacto recibido', [
                'nombre' => $validated['nombre'],
                'email' => $validated['email'],
                'asunto' => $validated['asunto'],
                'tipo_consulta' => $validated['tipo_consulta']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mensaje enviado exitosamente. Te responderemos a la brevedad.',
                'data' => [
                    'ticket_id' => 'CT-' . date('Y') . '-' . str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                    'tiempo_respuesta_estimado' => $this->obtenerTiempoRespuesta($validated['tipo_consulta'])
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de contacto inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al enviar mensaje de contacto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener información de contacto de la empresa
     */
    public function informacionEmpresa(): JsonResponse
    {
        try {
            $informacion = [
                'datos_empresa' => [
                    'nombre' => 'Mi Tienda Virtual S.A.C.',
                    'ruc' => '20123456789',
                    'direccion' => 'Av. Principal 123, Miraflores, Lima, Perú',
                    'codigo_postal' => '15074'
                ],
                'contacto' => [
                    'telefono_principal' => '+51 1 234-5678',
                    'telefono_ventas' => '+51 1 234-5679',
                    'whatsapp' => '+51 987 654 321',
                    'email_general' => 'contacto@mitienda.com',
                    'email_ventas' => 'ventas@mitienda.com',
                    'email_soporte' => 'soporte@mitienda.com'
                ],
                'horarios_atencion' => [
                    'telefono' => [
                        'lunes_viernes' => '9:00 AM - 6:00 PM',
                        'sabados' => '9:00 AM - 2:00 PM',
                        'domingos' => 'Cerrado'
                    ],
                    'whatsapp' => [
                        'lunes_viernes' => '8:00 AM - 8:00 PM',
                        'sabados' => '8:00 AM - 6:00 PM',
                        'domingos' => '10:00 AM - 4:00 PM'
                    ],
                    'email' => 'Respuesta en 24-48 horas'
                ],
                'ubicacion' => [
                    'latitud' => -12.1191427,
                    'longitud' => -77.0349046,
                    'direccion_completa' => 'Av. Principal 123, Miraflores 15074, Lima, Perú',
                    'referencias' => 'Frente al parque central, a dos cuadras del malecón'
                ],
                'redes_sociales' => [
                    'facebook' => 'https://facebook.com/mitienda',
                    'instagram' => 'https://instagram.com/mitienda',
                    'twitter' => 'https://twitter.com/mitienda',
                    'youtube' => 'https://youtube.com/mitienda',
                    'tiktok' => 'https://tiktok.com/@mitienda'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $informacion
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener información de empresa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener tipos de consulta disponibles
     */
    public function tiposConsulta(): JsonResponse
    {
        try {
            $tipos = [
                [
                    'value' => 'general',
                    'label' => 'Consulta General',
                    'descripcion' => 'Información general sobre productos y servicios',
                    'icono' => 'question-circle',
                    'tiempo_respuesta' => '24-48 horas'
                ],
                [
                    'value' => 'ventas',
                    'label' => 'Ventas',
                    'descripcion' => 'Cotizaciones, disponibilidad de productos, precios especiales',
                    'icono' => 'shopping-cart',
                    'tiempo_respuesta' => '2-4 horas'
                ],
                [
                    'value' => 'soporte',
                    'label' => 'Soporte Técnico',
                    'descripcion' => 'Ayuda con el uso de productos, instalación, configuración',
                    'icono' => 'tools',
                    'tiempo_respuesta' => '4-8 horas'
                ],
                [
                    'value' => 'reclamos',
                    'label' => 'Reclamos',
                    'descripcion' => 'Problemas con pedidos, productos defectuosos, devoluciones',
                    'icono' => 'exclamation-triangle',
                    'tiempo_respuesta' => '1-2 horas',
                    'prioridad' => 'alta'
                ],
                [
                    'value' => 'sugerencias',
                    'label' => 'Sugerencias',
                    'descripcion' => 'Ideas para mejorar nuestros productos y servicios',
                    'icono' => 'lightbulb',
                    'tiempo_respuesta' => '48-72 horas'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $tipos
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener tipos de consulta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener preguntas frecuentes relacionadas con contacto
     */
    public function faqContacto(): JsonResponse
    {
        try {
            $faq = [
                [
                    'id' => 1,
                    'pregunta' => '¿Cuál es el horario de atención telefónica?',
                    'respuesta' => 'Nuestro horario de atención telefónica es de lunes a viernes de 9:00 AM a 6:00 PM, y sábados de 9:00 AM a 2:00 PM.',
                    'categoria' => 'horarios'
                ],
                [
                    'id' => 2,
                    'pregunta' => '¿Cuánto tiempo tardan en responder mis consultas por email?',
                    'respuesta' => 'Respondemos las consultas por email en un plazo de 24 a 48 horas hábiles. Para consultas urgentes, recomendamos contactarnos por WhatsApp o teléfono.',
                    'categoria' => 'tiempos_respuesta'
                ],
                [
                    'id' => 3,
                    'pregunta' => '¿Puedo visitar su tienda física?',
                    'respuesta' => 'Sí, contamos con una tienda física ubicada en Av. Principal 123, Miraflores. Nuestro horario de atención es de lunes a domingo de 10:00 AM a 8:00 PM.',
                    'categoria' => 'tienda_fisica'
                ],
                [
                    'id' => 4,
                    'pregunta' => '¿Tienen servicio de WhatsApp?',
                    'respuesta' => 'Sí, tenemos atención por WhatsApp al +51 987 654 321. El horario es de lunes a viernes de 8:00 AM a 8:00 PM, sábados de 8:00 AM a 6:00 PM y domingos de 10:00 AM a 4:00 PM.',
                    'categoria' => 'whatsapp'
                ],
                [
                    'id' => 5,
                    'pregunta' => '¿Cómo puedo presentar un reclamo?',
                    'respuesta' => 'Puedes presentar un reclamo a través de nuestro formulario de contacto seleccionando "Reclamos" como tipo de consulta, o contactándonos directamente por teléfono para atención inmediata.',
                    'categoria' => 'reclamos'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $faq
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al obtener FAQ de contacto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Verificar estado del servicio de atención
     */
    public function estadoServicio(): JsonResponse
    {
        try {
            $horaActual = now()->setTimezone('America/Lima');
            $diaSemana = $horaActual->dayOfWeek; // 0 = domingo, 6 = sábado
            $hora = $horaActual->hour;

            $servicios = [
                'telefono' => [
                    'disponible' => $this->verificarDisponibilidadTelefono($diaSemana, $hora),
                    'mensaje' => $this->obtenerMensajeTelefono($diaSemana, $hora),
                    'proximo_horario' => $this->obtenerProximoHorarioTelefono($diaSemana, $hora)
                ],
                'whatsapp' => [
                    'disponible' => $this->verificarDisponibilidadWhatsApp($diaSemana, $hora),
                    'mensaje' => $this->obtenerMensajeWhatsApp($diaSemana, $hora),
                    'proximo_horario' => $this->obtenerProximoHorarioWhatsApp($diaSemana, $hora)
                ],
                'email' => [
                    'disponible' => true,
                    'mensaje' => 'Siempre disponible - Respuesta en 24-48 horas',
                    'tiempo_respuesta' => '24-48 horas'
                ],
                'tienda_fisica' => [
                    'disponible' => $this->verificarDisponibilidadTienda($diaSemana, $hora),
                    'mensaje' => $this->obtenerMensajeTienda($diaSemana, $hora),
                    'direccion' => 'Av. Principal 123, Miraflores, Lima'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'servicios' => $servicios,
                    'hora_actual' => $horaActual->format('Y-m-d H:i:s'),
                    'zona_horaria' => 'America/Lima'
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al verificar estado del servicio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Enviar email de contacto (simulado)
     */
    private function enviarEmailContacto(array $datos): void
    {
        // En producción, aquí se enviaría el email real
        // Mail::send('emails.contacto', $datos, function ($message) use ($datos) {
        //     $message->to('contacto@mitienda.com')
        //            ->subject('Nuevo mensaje de contacto: ' . $datos['asunto']);
        // });

        Log::info('Email de contacto enviado (simulado)', [
            'destinatario' => 'contacto@mitienda.com',
            'remitente' => $datos['email'],
            'asunto' => $datos['asunto']
        ]);
    }

    /**
     * Obtener tiempo de respuesta según tipo de consulta
     */
    private function obtenerTiempoRespuesta(string $tipoConsulta): string
    {
        $tiempos = [
            'general' => '24-48 horas',
            'ventas' => '2-4 horas',
            'soporte' => '4-8 horas',
            'reclamos' => '1-2 horas',
            'sugerencias' => '48-72 horas'
        ];

        return $tiempos[$tipoConsulta] ?? '24-48 horas';
    }

    /**
     * Verificar disponibilidad del teléfono
     */
    private function verificarDisponibilidadTelefono(int $dia, int $hora): bool
    {
        // Lunes a viernes: 9:00 - 18:00
        if ($dia >= 1 && $dia <= 5) {
            return $hora >= 9 && $hora < 18;
        }
        // Sábado: 9:00 - 14:00
        if ($dia === 6) {
            return $hora >= 9 && $hora < 14;
        }
        // Domingo: cerrado
        return false;
    }

    /**
     * Verificar disponibilidad de WhatsApp
     */
    private function verificarDisponibilidadWhatsApp(int $dia, int $hora): bool
    {
        // Lunes a viernes: 8:00 - 20:00
        if ($dia >= 1 && $dia <= 5) {
            return $hora >= 8 && $hora < 20;
        }
        // Sábado: 8:00 - 18:00
        if ($dia === 6) {
            return $hora >= 8 && $hora < 18;
        }
        // Domingo: 10:00 - 16:00
        if ($dia === 0) {
            return $hora >= 10 && $hora < 16;
        }
        return false;
    }

    /**
     * Verificar disponibilidad de tienda física
     */
    private function verificarDisponibilidadTienda(int $dia, int $hora): bool
    {
        // Todos los días: 10:00 - 20:00
        return $hora >= 10 && $hora < 20;
    }

    /**
     * Obtener mensaje de estado del teléfono
     */
    private function obtenerMensajeTelefono(int $dia, int $hora): string
    {
        if ($this->verificarDisponibilidadTelefono($dia, $hora)) {
            return 'Disponible ahora';
        }
        return 'Fuera del horario de atención';
    }

    /**
     * Obtener mensaje de estado de WhatsApp
     */
    private function obtenerMensajeWhatsApp(int $dia, int $hora): string
    {
        if ($this->verificarDisponibilidadWhatsApp($dia, $hora)) {
            return 'Disponible ahora';
        }
        return 'Fuera del horario de atención';
    }

    /**
     * Obtener mensaje de estado de tienda
     */
    private function obtenerMensajeTienda(int $dia, int $hora): string
    {
        if ($this->verificarDisponibilidadTienda($dia, $hora)) {
            return 'Abierto ahora';
        }
        return 'Cerrado';
    }

    /**
     * Obtener próximo horario de teléfono
     */
    private function obtenerProximoHorarioTelefono(int $dia, int $hora): string
    {
        if ($this->verificarDisponibilidadTelefono($dia, $hora)) {
            return '';
        }
        
        // Lógica simplificada para próximo horario
        if ($dia === 0 || ($dia === 6 && $hora >= 14)) {
            return 'Lunes 9:00 AM';
        }
        if ($dia >= 1 && $dia <= 5 && $hora < 9) {
            return 'Hoy 9:00 AM';
        }
        if ($dia >= 1 && $dia <= 5 && $hora >= 18) {
            return 'Mañana 9:00 AM';
        }
        if ($dia === 6 && $hora < 9) {
            return 'Hoy 9:00 AM';
        }
        
        return 'Lunes 9:00 AM';
    }

    /**
     * Obtener próximo horario de WhatsApp
     */
    private function obtenerProximoHorarioWhatsApp(int $dia, int $hora): string
    {
        if ($this->verificarDisponibilidadWhatsApp($dia, $hora)) {
            return '';
        }
        
        // Lógica simplificada para próximo horario
        if ($dia >= 1 && $dia <= 5 && $hora < 8) {
            return 'Hoy 8:00 AM';
        }
        if ($dia === 6 && $hora < 8) {
            return 'Hoy 8:00 AM';
        }
        if ($dia === 0 && $hora < 10) {
            return 'Hoy 10:00 AM';
        }
        
        return 'Mañana 8:00 AM';
    }
} 