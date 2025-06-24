<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePedidoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $pedido = $this->route('pedido');
        
        return [
            // Estado del pedido
            'estado' => [
                'sometimes',
                'string',
                'in:pendiente,aprobado,rechazado,en_proceso,enviado,entregado,cancelado,devuelto'
            ],

            // Tipo de pago (solo si está pendiente)
            'tipo_pago' => [
                'sometimes',
                'string',
                'in:contado,credito,transferencia,tarjeta,yape,plin,paypal',
                function ($attribute, $value, $fail) use ($pedido) {
                    if ($pedido && $pedido->estado !== 'pendiente') {
                        $fail('No se puede cambiar el tipo de pago de un pedido que no está pendiente.');
                    }
                }
            ],

            // Cuotas (solo para crédito)
            'cuotas' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:24',
                'required_if:tipo_pago,credito'
            ],

            // Información de seguimiento
            'codigo_rastreo' => [
                'sometimes',
                'nullable',
                'string',
                'max:100'
            ],

            // Observaciones
            'observaciones' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000'
            ],

            // Canal de venta
            'canal_venta' => [
                'sometimes',
                'nullable',
                'string',
                'in:web,app,tienda_fisica,telefono,whatsapp',
                'max:50'
            ],

            // Moneda (solo si está pendiente)
            'moneda' => [
                'sometimes',
                'nullable',
                'string',
                'in:PEN,USD,EUR',
                'max:5',
                function ($attribute, $value, $fail) use ($pedido) {
                    if ($pedido && $pedido->estado !== 'pendiente') {
                        $fail('No se puede cambiar la moneda de un pedido que no está pendiente.');
                    }
                }
            ],

            // Descuento total (solo administradores)
            'descuento_total' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99999.99'
            ],

            // Total (solo para ajustes administrativos)
            'total' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:999999.99',
                function ($attribute, $value, $fail) use ($pedido) {
                    if ($pedido && in_array($pedido->estado, ['entregado', 'cancelado', 'devuelto'])) {
                        $fail('No se puede modificar el total de un pedido finalizado.');
                    }
                }
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Estado
            'estado.in' => 'El estado debe ser: pendiente, aprobado, rechazado, en_proceso, enviado, entregado, cancelado o devuelto.',

            // Tipo de pago
            'tipo_pago.in' => 'El tipo de pago debe ser: contado, crédito, transferencia, tarjeta, yape, plin o paypal.',
            'cuotas.required_if' => 'Las cuotas son requeridas para pagos a crédito.',
            'cuotas.min' => 'El número de cuotas debe ser al menos 1.',
            'cuotas.max' => 'El número de cuotas no puede ser mayor a 24.',

            // Información de seguimiento
            'codigo_rastreo.max' => 'El código de rastreo no puede exceder 100 caracteres.',
            'observaciones.max' => 'Las observaciones no pueden exceder 1000 caracteres.',

            // Canal y moneda
            'canal_venta.in' => 'El canal de venta debe ser: web, app, tienda_fisica, telefono o whatsapp.',
            'moneda.in' => 'La moneda debe ser PEN, USD o EUR.',

            // Valores monetarios
            'descuento_total.min' => 'El descuento total no puede ser negativo.',
            'descuento_total.max' => 'El descuento total no puede exceder 99,999.99.',
            'total.min' => 'El total no puede ser negativo.',
            'total.max' => 'El total no puede exceder 999,999.99.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'estado' => 'estado del pedido',
            'tipo_pago' => 'tipo de pago',
            'cuotas' => 'número de cuotas',
            'codigo_rastreo' => 'código de rastreo',
            'observaciones' => 'observaciones',
            'canal_venta' => 'canal de venta',
            'moneda' => 'moneda',
            'descuento_total' => 'descuento total',
            'total' => 'total del pedido',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $pedido = $this->route('pedido');
            
            if (!$pedido) {
                return;
            }

            // Validar transiciones de estado válidas
            if ($this->has('estado')) {
                $estadoActual = $pedido->estado;
                $nuevoEstado = $this->estado;

                $transicionesValidas = [
                    'pendiente' => ['aprobado', 'rechazado', 'cancelado'],
                    'aprobado' => ['en_proceso', 'cancelado'],
                    'en_proceso' => ['enviado', 'cancelado'],
                    'enviado' => ['entregado', 'devuelto'],
                    'entregado' => ['devuelto'],
                    'rechazado' => [],
                    'cancelado' => [],
                    'devuelto' => []
                ];

                if (!in_array($nuevoEstado, $transicionesValidas[$estadoActual] ?? [])) {
                    $validator->errors()->add(
                        'estado',
                        "No se puede cambiar de estado '{$estadoActual}' a '{$nuevoEstado}'. Transiciones válidas: " . 
                        implode(', ', $transicionesValidas[$estadoActual] ?? [])
                    );
                }

                // Validar código de rastreo requerido para estado 'enviado'
                if ($nuevoEstado === 'enviado' && !$this->codigo_rastreo && !$pedido->codigo_rastreo) {
                    $validator->errors()->add(
                        'codigo_rastreo',
                        'Se requiere código de rastreo para marcar el pedido como enviado.'
                    );
                }
            }

            // Validar que no se modifiquen campos críticos en pedidos finalizados
            $estadosFinalizados = ['entregado', 'cancelado', 'devuelto'];
            if (in_array($pedido->estado, $estadosFinalizados)) {
                $camposRestringidos = ['tipo_pago', 'cuotas', 'moneda'];
                foreach ($camposRestringidos as $campo) {
                    if ($this->has($campo)) {
                        $validator->errors()->add(
                            $campo,
                            "No se puede modificar '{$campo}' en un pedido con estado '{$pedido->estado}'."
                        );
                    }
                }
            }

            // Validar límite de crédito si se cambia a crédito
            if ($this->tipo_pago === 'credito' && $pedido->user_id) {
                $usuario = \App\Models\User::find($pedido->user_id);
                if ($usuario && $usuario->limite_credito <= 0) {
                    $validator->errors()->add(
                        'tipo_pago',
                        'El usuario no tiene límite de crédito disponible.'
                    );
                }
            }

            // Validar que el total no sea menor que el descuento
            if ($this->has('total') && $this->has('descuento_total')) {
                if ($this->total < $this->descuento_total) {
                    $validator->errors()->add(
                        'total',
                        'El total no puede ser menor que el descuento total.'
                    );
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalizar datos si están presentes
        $datosNormalizados = [];

        if ($this->has('estado')) {
            $datosNormalizados['estado'] = strtolower($this->estado);
        }

        if ($this->has('tipo_pago')) {
            $datosNormalizados['tipo_pago'] = strtolower($this->tipo_pago);
        }

        if ($this->has('moneda')) {
            $datosNormalizados['moneda'] = strtoupper($this->moneda);
        }

        if ($this->has('canal_venta')) {
            $datosNormalizados['canal_venta'] = strtolower($this->canal_venta);
        }

        if ($this->has('codigo_rastreo')) {
            $datosNormalizados['codigo_rastreo'] = strtoupper(trim($this->codigo_rastreo));
        }

        // Limpiar observaciones
        if ($this->has('observaciones')) {
            $datosNormalizados['observaciones'] = trim($this->observaciones);
        }

        // Convertir valores numéricos
        if ($this->has('cuotas')) {
            $datosNormalizados['cuotas'] = $this->cuotas ? (int) $this->cuotas : null;
        }

        if ($this->has('descuento_total')) {
            $datosNormalizados['descuento_total'] = $this->descuento_total ? (float) $this->descuento_total : null;
        }

        if ($this->has('total')) {
            $datosNormalizados['total'] = (float) $this->total;
        }

        if (!empty($datosNormalizados)) {
            $this->merge($datosNormalizados);
        }
    }
} 