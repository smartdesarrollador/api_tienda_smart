<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePagoRequest extends FormRequest
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
        $pago = $this->route('pago');
        
        return [
            // Monto del pago (solo si no está pagado)
            'monto' => [
                'sometimes',
                'numeric',
                'min:0.01',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/', // Máximo 2 decimales
                function ($attribute, $value, $fail) use ($pago) {
                    if ($pago && $pago->estado === 'pagado') {
                        $fail('No se puede modificar el monto de un pago ya completado.');
                    }
                }
            ],

            // Número de cuota (solo si no está pagado)
            'numero_cuota' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:24',
                function ($attribute, $value, $fail) use ($pago) {
                    if ($pago && $pago->estado === 'pagado') {
                        $fail('No se puede modificar el número de cuota de un pago ya completado.');
                    }
                }
            ],

            // Fecha del pago
            'fecha_pago' => [
                'sometimes',
                'date',
                'before_or_equal:today',
                'after:2020-01-01'
            ],

            // Estado del pago
            'estado' => [
                'sometimes',
                'string',
                'in:pendiente,pagado,atrasado,fallido'
            ],

            // Método de pago (solo si no está pagado)
            'metodo' => [
                'sometimes',
                'string',
                'in:efectivo,tarjeta,transferencia,yape,plin,paypal,cuota',
                function ($attribute, $value, $fail) use ($pago) {
                    if ($pago && $pago->estado === 'pagado') {
                        $fail('No se puede modificar el método de un pago ya completado.');
                    }
                }
            ],

            // Referencia de transacción
            'referencia' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9\-_]+$/'
            ],

            // Moneda (solo si no está pagado)
            'moneda' => [
                'sometimes',
                'string',
                'in:PEN,USD,EUR',
                'size:3',
                function ($attribute, $value, $fail) use ($pago) {
                    if ($pago && $pago->estado === 'pagado') {
                        $fail('No se puede modificar la moneda de un pago ya completado.');
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
            // Monto
            'monto.numeric' => 'El monto debe ser un número válido.',
            'monto.min' => 'El monto debe ser mayor a 0.',
            'monto.max' => 'El monto no puede exceder 999,999.99.',
            'monto.regex' => 'El monto debe tener máximo 2 decimales.',

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
            'metodo.in' => 'El método de pago debe ser: efectivo, tarjeta, transferencia, yape, plin, paypal o cuota.',

            // Referencia
            'referencia.max' => 'La referencia no puede exceder 100 caracteres.',
            'referencia.regex' => 'La referencia solo puede contener letras, números, guiones y guiones bajos.',

            // Moneda
            'moneda.in' => 'La moneda debe ser PEN, USD o EUR.',
            'moneda.size' => 'La moneda debe tener exactamente 3 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
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

        if (!empty($datosNormalizados)) {
            $this->merge($datosNormalizados);
        }
    }

    /**
     * Validaciones adicionales de lógica de negocio.
     */
    private function validarLogicaNegocio($validator): void
    {
        $pago = $this->route('pago');
        
        if (!$pago) {
            return;
        }

        // Validar que si es método 'cuota', debe tener numero_cuota
        if ($this->has('metodo') && $this->metodo === 'cuota' && !$this->numero_cuota && !$pago->numero_cuota) {
            $validator->errors()->add('numero_cuota', 'El número de cuota es obligatorio para pagos de cuota.');
        }

        // Validar que métodos digitales requieren referencia
        $metodosQueRequierenReferencia = ['transferencia', 'yape', 'plin', 'paypal'];
        $metodoActual = $this->has('metodo') ? $this->metodo : $pago->metodo;
        
        if (in_array($metodoActual, $metodosQueRequierenReferencia)) {
            $referenciaActual = $this->has('referencia') ? $this->referencia : $pago->referencia;
            if (!$referenciaActual) {
                $validator->errors()->add('referencia', 'La referencia es obligatoria para este método de pago.');
            }
        }

        // Validar que el nuevo monto no exceda el saldo pendiente del pedido
        if ($this->has('monto') && $this->monto) {
            $pedido = $pago->pedido;
            if ($pedido) {
                // Calcular total pagado excluyendo este pago
                $totalPagadoSinEstePago = $pedido->pagos()
                    ->where('estado', 'pagado')
                    ->where('id', '!=', $pago->id)
                    ->sum('monto');
                
                $saldoPendiente = $pedido->total - $totalPagadoSinEstePago;
                
                if ($this->monto > $saldoPendiente) {
                    $validator->errors()->add('monto', "El monto excede el saldo pendiente del pedido (S/ {$saldoPendiente}).");
                }
            }
        }

        // Validar que si es cuota de crédito, el pedido sea de tipo crédito
        $numeroCuotaActual = $this->has('numero_cuota') ? $this->numero_cuota : $pago->numero_cuota;
        if ($numeroCuotaActual && $pago->pedido->tipo_pago !== 'credito') {
            $validator->errors()->add('numero_cuota', 'Solo los pedidos a crédito pueden tener cuotas.');
        }

        // Validar que la nueva cuota no esté ya pagada por otro pago
        if ($this->has('numero_cuota') && $this->numero_cuota && $this->numero_cuota !== $pago->numero_cuota) {
            $cuotaExistente = \App\Models\CuotaCredito::where('pedido_id', $pago->pedido_id)
                ->where('numero_cuota', $this->numero_cuota)
                ->where('estado', 'pagado')
                ->first();
                
            if ($cuotaExistente) {
                $validator->errors()->add('numero_cuota', 'Esta cuota ya está pagada por otro pago.');
            }
        }

        // Validar transiciones de estado válidas
        if ($this->has('estado') && $this->estado !== $pago->estado) {
            $transicionesValidas = [
                'pendiente' => ['pagado', 'fallido', 'atrasado'],
                'atrasado' => ['pagado', 'fallido'],
                'fallido' => ['pendiente', 'atrasado'],
                'pagado' => [] // No se puede cambiar desde pagado
            ];

            $estadosPermitidos = $transicionesValidas[$pago->estado] ?? [];
            
            if (!in_array($this->estado, $estadosPermitidos)) {
                $validator->errors()->add('estado', "No se puede cambiar el estado de '{$pago->estado}' a '{$this->estado}'.");
            }
        }

        // Validar que no se puede modificar un pago de cuota que ya tiene una cuota de crédito pagada
        if ($pago->numero_cuota && $pago->estado === 'pagado') {
            $cuotaCredito = \App\Models\CuotaCredito::where('pedido_id', $pago->pedido_id)
                ->where('numero_cuota', $pago->numero_cuota)
                ->where('estado', 'pagado')
                ->first();
                
            if ($cuotaCredito && ($this->has('estado') && $this->estado !== 'pagado')) {
                $validator->errors()->add('estado', 'No se puede modificar un pago que tiene una cuota de crédito asociada ya pagada.');
            }
        }
    }
} 