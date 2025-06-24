<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePagoRequest extends FormRequest
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
        return [
            // Pedido relacionado
            'pedido_id' => [
                'required',
                'integer',
                'exists:pedidos,id',
                Rule::exists('pedidos', 'id')->where(function ($query) {
                    $query->whereIn('estado', ['pendiente', 'aprobado', 'en_proceso', 'enviado']);
                })
            ],

            // Método de pago (nuevo campo)
            'metodo_pago_id' => [
                'nullable',
                'integer',
                'exists:metodos_pago,id',
                Rule::exists('metodos_pago', 'id')->where(function ($query) {
                    $query->where('activo', true);
                })
            ],

            // Monto del pago
            'monto' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/' // Máximo 2 decimales
            ],

            // Comisión (calculada automáticamente, pero puede ser enviada)
            'comision' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999.99'
            ],

            // Número de cuota (opcional, solo para créditos)
            'numero_cuota' => [
                'nullable',
                'integer',
                'min:1',
                'max:24'
            ],

            // Fecha del pago
            'fecha_pago' => [
                'nullable',
                'date',
                'before_or_equal:today',
                'after:2020-01-01' // Fecha mínima razonable
            ],

            // Estado del pago
            'estado' => [
                'nullable',
                'string',
                'in:pendiente,pagado,atrasado,fallido'
            ],

            // Método de pago
            'metodo' => [
                'required',
                'string',
                'in:efectivo,tarjeta,transferencia,yape,plin,paypal,cuota'
            ],

            // Referencia de transacción
            'referencia' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9\-_]+$/' // Solo alfanuméricos, guiones y guiones bajos
            ],

            // Moneda
            'moneda' => [
                'nullable',
                'string',
                'in:PEN,USD,EUR',
                'size:3'
            ],

            // Nuevos campos de respuesta del proveedor
            'respuesta_proveedor' => [
                'nullable',
                'array'
            ],

            // Código de autorización
            'codigo_autorizacion' => [
                'nullable',
                'string',
                'max:100'
            ],

            // Observaciones adicionales
            'observaciones' => [
                'nullable',
                'string',
                'max:1000'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Pedido
            'pedido_id.required' => 'El ID del pedido es obligatorio.',
            'pedido_id.exists' => 'El pedido especificado no existe o no puede recibir pagos.',

            // Método de pago
            'metodo_pago_id.exists' => 'El método de pago seleccionado no existe o está inactivo.',

            // Monto
            'monto.required' => 'El monto del pago es obligatorio.',
            'monto.numeric' => 'El monto debe ser un número válido.',
            'monto.min' => 'El monto debe ser mayor a 0.',
            'monto.max' => 'El monto no puede exceder 999,999.99.',
            'monto.regex' => 'El monto debe tener máximo 2 decimales.',

            // Comisión
            'comision.numeric' => 'La comisión debe ser un número válido.',
            'comision.min' => 'La comisión no puede ser negativa.',
            'comision.max' => 'La comisión no puede exceder 99,999.99.',

            // Número de cuota
            'numero_cuota.integer' => 'El número de cuota debe ser un entero.',
            'numero_cuota.min' => 'El número de cuota debe ser mayor a 0.',
            'numero_cuota.max' => 'El número de cuota no puede ser mayor a 24.',

            // Fecha de pago
            'fecha_pago.date' => 'La fecha de pago debe ser una fecha válida.',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser futura.',
            'fecha_pago.after' => 'La fecha de pago debe ser posterior al 1 de enero de 2020.',

            // Estado
            'estado.in' => 'El estado del pago debe ser: pendiente, pagado, atrasado o fallido.',

            // Método
            'metodo.required' => 'El método de pago es obligatorio.',
            'metodo.in' => 'El método de pago debe ser: efectivo, tarjeta, transferencia, yape, plin, paypal o cuota.',

            // Referencia
            'referencia.max' => 'La referencia no puede exceder 100 caracteres.',
            'referencia.regex' => 'La referencia solo puede contener letras, números, guiones y guiones bajos.',

            // Moneda
            'moneda.in' => 'La moneda debe ser PEN, USD o EUR.',
            'moneda.size' => 'La moneda debe tener exactamente 3 caracteres.',

            // Código de autorización
            'codigo_autorizacion.max' => 'El código de autorización no puede exceder 100 caracteres.',

            // Observaciones
            'observaciones.max' => 'Las observaciones no pueden exceder 1000 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'pedido_id' => 'pedido',
            'numero_cuota' => 'número de cuota',
            'fecha_pago' => 'fecha de pago',
            'metodo' => 'método de pago',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validarLogicaNegocio($validator);
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $datosNormalizados = [];

        // Normalizar método de pago
        if ($this->has('metodo')) {
            $datosNormalizados['metodo'] = strtolower(trim($this->metodo));
        }

        // Normalizar moneda
        if ($this->has('moneda')) {
            $datosNormalizados['moneda'] = strtoupper(trim($this->moneda));
        }

        // Normalizar estado
        if ($this->has('estado')) {
            $datosNormalizados['estado'] = strtolower(trim($this->estado));
        }

        // Normalizar referencia
        if ($this->has('referencia')) {
            $datosNormalizados['referencia'] = strtoupper(trim($this->referencia));
        }

        // Convertir valores numéricos
        if ($this->has('monto')) {
            $datosNormalizados['monto'] = (float) $this->monto;
        }

        if ($this->has('numero_cuota')) {
            $datosNormalizados['numero_cuota'] = $this->numero_cuota ? (int) $this->numero_cuota : null;
        }

        if ($this->has('pedido_id')) {
            $datosNormalizados['pedido_id'] = (int) $this->pedido_id;
        }

        if (!empty($datosNormalizados)) {
            $this->merge($datosNormalizados);
        }
    }

    /**
     * Validaciones adicionales de lógica de negocio.
     */
    private function validarLogicaNegocio($validator): void
    {
        // Validar que si es método 'cuota', debe tener numero_cuota
        if ($this->metodo === 'cuota' && !$this->numero_cuota) {
            $validator->errors()->add('numero_cuota', 'El número de cuota es obligatorio para pagos de cuota.');
        }

        // Validar que métodos digitales requieren referencia
        $metodosQueRequierenReferencia = ['transferencia', 'yape', 'plin', 'paypal'];
        if (in_array($this->metodo, $metodosQueRequierenReferencia) && !$this->referencia) {
            $validator->errors()->add('referencia', 'La referencia es obligatoria para este método de pago.');
        }

        // Validar que el monto no exceda el saldo pendiente del pedido
        if ($this->pedido_id && $this->monto) {
            $pedido = \App\Models\Pedido::find($this->pedido_id);
            if ($pedido) {
                $totalPagado = $pedido->pagos()->where('estado', 'pagado')->sum('monto');
                $saldoPendiente = $pedido->total - $totalPagado;
                
                if ($this->monto > $saldoPendiente) {
                    $validator->errors()->add('monto', "El monto excede el saldo pendiente del pedido (S/ {$saldoPendiente}).");
                }
            }
        }

        // Validar que si es cuota de crédito, el pedido sea de tipo crédito
        if ($this->numero_cuota && $this->pedido_id) {
            $pedido = \App\Models\Pedido::find($this->pedido_id);
            if ($pedido && $pedido->tipo_pago !== 'credito') {
                $validator->errors()->add('numero_cuota', 'Solo los pedidos a crédito pueden tener cuotas.');
            }
        }

        // Validar que la cuota no esté ya pagada
        if ($this->numero_cuota && $this->pedido_id) {
            $cuotaExistente = \App\Models\CuotaCredito::where('pedido_id', $this->pedido_id)
                ->where('numero_cuota', $this->numero_cuota)
                ->where('estado', 'pagado')
                ->first();
                
            if ($cuotaExistente) {
                $validator->errors()->add('numero_cuota', 'Esta cuota ya está pagada.');
            }
        }
    }
} 