<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class IzipayService
{
    private string $username;
    private string $password;
    private string $publicKey;
    private string $sha256Key;
    private string $apiUrl;

    public function __construct()
    {
        $this->username = config('services.izipay.username');
        $this->password = config('services.izipay.password');
        $this->publicKey = config('services.izipay.public_key');
        $this->sha256Key = config('services.izipay.sha256_key');
        $this->apiUrl = config('services.izipay.api_url', 'https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment');

        // Verificar que las credenciales estén configuradas
        if (empty($this->username) || empty($this->password) || empty($this->publicKey) || empty($this->sha256Key)) {
            Log::warning('Izipay: Credenciales no configuradas correctamente', [
                'username_set' => !empty($this->username),
                'password_set' => !empty($this->password),
                'public_key_set' => !empty($this->publicKey),
                'sha256_key_set' => !empty($this->sha256Key)
            ]);
        }
    }

    /**
     * Generar formToken para Izipay
     */
    public function generarFormToken(array $datosVenta): array
    {
        try {
            $headers = [
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                'Content-Type' => 'application/json'
            ];

            $body = [
                'amount' => $datosVenta['amount'] * 100, // Convertir a centavos
                'currency' => $datosVenta['currency'] ?? 'PEN',
                'orderId' => $datosVenta['orderId'],
                'customer' => [
                    'email' => $datosVenta['customer']['email'],
                    'billingDetails' => [
                        'firstName' => $datosVenta['customer']['firstName'],
                        'lastName' => $datosVenta['customer']['lastName'],
                        'phoneNumber' => $datosVenta['customer']['phoneNumber'],
                        'identityType' => $datosVenta['customer']['identityType'] ?? 'DNI',
                        'identityCode' => $datosVenta['customer']['identityCode'],
                        'address' => $datosVenta['customer']['address'],
                        'country' => $datosVenta['customer']['country'] ?? 'PE',
                        'city' => $datosVenta['customer']['city'],
                        'state' => $datosVenta['customer']['state'],
                        'zipCode' => $datosVenta['customer']['zipCode']
                    ]
                ]
            ];

            Log::info('Izipay: Generando formToken', [
                'order_id' => $datosVenta['orderId'],
                'amount' => $datosVenta['amount'],
                'customer_email' => $datosVenta['customer']['email']
            ]);

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->withOptions([
                    'verify' => false, // Solo para desarrollo - NUNCA en producción
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]
                ])
                ->post($this->apiUrl, $body);

            if (!$response->successful()) {
                throw new Exception('Error en la API de Izipay: ' . $response->body());
            }

            $responseData = $response->json();

            if (!isset($responseData['answer']['formToken'])) {
                throw new Exception('formToken no encontrado en la respuesta de Izipay');
            }

            $formToken = $responseData['answer']['formToken'];
            // El publicKey ya viene con el formato correcto desde la configuración
            $publicKey = $this->publicKey;

            Log::info('Izipay: FormToken generado exitosamente', [
                'order_id' => $datosVenta['orderId'],
                'form_token' => substr($formToken, 0, 20) . '...',
                'public_key_format' => substr($publicKey, 0, 20) . '...'
            ]);

            return [
                'formToken' => $formToken,
                'publicKey' => $publicKey,
                'success' => true
            ];

        } catch (Exception $e) {
            Log::error('Izipay: Error al generar formToken', [
                'error' => $e->getMessage(),
                'order_id' => $datosVenta['orderId'] ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            throw new Exception('Error al generar formToken de Izipay: ' . $e->getMessage());
        }
    }

    /**
     * Validar firma de respuesta de Izipay
     * Para respuestas de formulario usar sha256Key, para IPN usar password
     */
    public function validarFirma(array $datosRespuesta, bool $esIPN = false): bool
    {
        try {
            if (!isset($datosRespuesta['kr-answer']) || !isset($datosRespuesta['kr-hash'])) {
                Log::warning('Izipay: Datos de respuesta incompletos', $datosRespuesta);
                return false;
            }

            $krAnswer = str_replace('\/', '/', $datosRespuesta['kr-answer']);
            $krHash = $datosRespuesta['kr-hash'];

            // Usar password para IPN, sha256Key para respuestas de formulario
            $key = $esIPN ? $this->password : $this->sha256Key;
            $calculatedHash = hash_hmac('sha256', $krAnswer, $key);

            $isValid = hash_equals($calculatedHash, $krHash);

            Log::info('Izipay: Validación de firma', [
                'is_valid' => $isValid,
                'es_ipn' => $esIPN,
                'key_used' => $esIPN ? 'password' : 'sha256Key',
                'provided_hash' => substr($krHash, 0, 20) . '...',
                'calculated_hash' => substr($calculatedHash, 0, 20) . '...'
            ]);

            return $isValid;

        } catch (Exception $e) {
            Log::error('Izipay: Error al validar firma', [
                'error' => $e->getMessage(),
                'datos' => $datosRespuesta
            ]);
            return false;
        }
    }

    /**
     * Procesar respuesta de pago de Izipay
     */
    public function procesarRespuestaPago(array $datosRespuesta): array
    {
        try {
            if (!$this->validarFirma($datosRespuesta, false)) {
                throw new Exception('Firma de Izipay inválida');
            }

            $krAnswer = json_decode($datosRespuesta['kr-answer'], true);

            if (!$krAnswer) {
                throw new Exception('Respuesta de Izipay inválida');
            }

            $orderStatus = $krAnswer['orderStatus'] ?? 'UNPAID';
            $transactionUuid = $krAnswer['transactions'][0]['uuid'] ?? null;
            $orderId = $krAnswer['orderDetails']['orderId'] ?? null;
            $amount = isset($krAnswer['orderDetails']['orderTotalAmount']) ? 
                      $krAnswer['orderDetails']['orderTotalAmount'] / 100 : 0;

            Log::info('Izipay: Respuesta de pago procesada', [
                'order_id' => $orderId,
                'order_status' => $orderStatus,
                'transaction_uuid' => $transactionUuid,
                'amount' => $amount
            ]);

            return [
                'success' => $orderStatus === 'PAID',
                'order_status' => $orderStatus,
                'transaction_uuid' => $transactionUuid,
                'order_id' => $orderId,
                'amount' => $amount,
                'raw_response' => $krAnswer
            ];

        } catch (Exception $e) {
            Log::error('Izipay: Error al procesar respuesta de pago', [
                'error' => $e->getMessage(),
                'datos' => $datosRespuesta
            ]);

            throw $e;
        }
    }

    /**
     * Procesar IPN (Instant Payment Notification)
     */
    public function procesarIPN(array $datosIPN): array
    {
        try {
            // Para IPN usamos el método validarFirma con flag de IPN
            if (!$this->validarFirma($datosIPN, true)) {
                throw new Exception('Firma de IPN inválida');
            }

            $krAnswer = str_replace('\/', '/', $datosIPN['kr-answer']);

            $answer = json_decode($krAnswer, true);
            $transaction = $answer['transactions'][0] ?? null;

            $orderStatus = $answer['orderStatus'] ?? 'UNPAID';
            $orderId = $answer['orderDetails']['orderId'] ?? null;
            $transactionUuid = $transaction['uuid'] ?? null;

            Log::info('Izipay: IPN procesado', [
                'order_id' => $orderId,
                'order_status' => $orderStatus,
                'transaction_uuid' => $transactionUuid
            ]);

            return [
                'success' => true,
                'order_status' => $orderStatus,
                'order_id' => $orderId,
                'transaction_uuid' => $transactionUuid,
                'raw_data' => $answer
            ];

        } catch (Exception $e) {
            Log::error('Izipay: Error al procesar IPN', [
                'error' => $e->getMessage(),
                'datos' => $datosIPN
            ]);

            throw $e;
        }
    }

    /**
     * Verificar configuración de Izipay
     */
    public function verificarConfiguracion(): array
    {
        $configuracion = [
            'username_configurado' => !empty($this->username),
            'password_configurado' => !empty($this->password),
            'public_key_configurado' => !empty($this->publicKey),
            'sha256_key_configurado' => !empty($this->sha256Key),
            'api_url' => $this->apiUrl
        ];

        $configuracion['configuracion_completa'] = 
            $configuracion['username_configurado'] &&
            $configuracion['password_configurado'] &&
            $configuracion['public_key_configurado'] &&
            $configuracion['sha256_key_configurado'];

        return $configuracion;
    }
} 