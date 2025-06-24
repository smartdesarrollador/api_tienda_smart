<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCuotaCreditoRequest extends FormRequest
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
                    $query->where('tipo_pago', 'credito');
                })
            ],

            // Número de cuota
            'numero_cuota' => [
                'required',
                'integer',
                'min:1',
                'max:24'
            ],

            // Monto de la cuota
            'monto_cuota' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/' // Máximo 2 decimales
            ],

            // Interés de la cuota
            'interes' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999.99',
                'regex:/^\d+(\.\d{1,2})?$/'
            ],

            // Mora de la cuota
            'mora' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999.99',
                'regex:/^\d+(\.\d{1,2})?$/'
            ],

            // Fecha de vencimiento
            'fecha_vencimiento' => [
                'required',
                'date',
                'after:today',
                'before:' . now()->addYears(5)->format('Y-m-d') // Máximo 5 años
            ],

            // Fecha de pago (opcional)
            'fecha_pago' => [
                'nullable',
                'date',
                'before_or_equal:today',
                'after:2020-01-01'
            ],

            // Estado de la cuota
            'estado' => [
                'nullable',
                'string',
                'in:pendiente,pagado,atrasado,condonado'
            ],

            // Moneda
            'moneda' => [
                'nullable',
                'string',
                'in:PEN,USD,EUR',
                'size:3'
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
            'pedido_id.exists' => 'El pedido especificado no existe o no es de tipo crédito.',

            // Número de cuota
            'numero_cuota.required' => 'El número de cuota es obligatorio.',
            'numero_cuota.integer' => 'El número de cuota debe ser un entero.',
            'numero_cuota.min' => 'El número de cuota debe ser mayor a 0.',
            'numero_cuota.max' => 'El número de cuota no puede ser mayor a 24.',

            // Monto de cuota
            'monto_cuota.required' => 'El monto de la cuota es obligatorio.',
            'monto_cuota.numeric' => 'El monto de la cuota debe ser un número válido.',
            'monto_cuota.min' => 'El monto de la cuota debe ser mayor a 0.',
            'monto_cuota.max' => 'El monto de la cuota no puede exceder 999,999.99.',
            'monto_cuota.regex' => 'El monto de la cuota debe tener máximo 2 decimales.',

            // Interés
            'interes.numeric' => 'El interés debe ser un número válido.',
            'interes.min' => 'El interés no puede ser negativo.',
            'interes.max' => 'El interés no puede exceder 99,999.99.',
            'interes.regex' => 'El interés debe tener máximo 2 decimales.',

            // Mora
            'mora.numeric' => 'La mora debe ser un número válido.',
            'mora.min' => 'La mora no puede ser negativa.',
            'mora.max' => 'La mora no puede exceder 99,999.99.',
            'mora.regex' => 'La mora debe tener máximo 2 decimales.',

            // Fecha de vencimiento
            'fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
            'fecha_vencimiento.date' => 'La fecha de vencimiento debe ser una fecha válida.',
            'fecha_vencimiento.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
            'fecha_vencimiento.before' => 'La fecha de vencimiento no puede ser mayor a 5 años.',

            // Fecha de pago
            'fecha_pago.date' => 'La fecha de pago debe ser una fecha válida.',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser futura.',
            'fecha_pago.after' => 'La fecha de pago debe ser posterior al 1 de enero de 2020.',

            // Estado
            'estado.in' => 'El estado debe ser: pendiente, pagado, atrasado o condonado.',

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
            'pedido_id' => 'pedido',
            'numero_cuota' => 'número de cuota',
            'monto_cuota' => 'monto de la cuota',
            'fecha_vencimiento' => 'fecha de vencimiento',
            'fecha_pago' => 'fecha de pago',
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

        // Normalizar moneda
        if ($this->has('moneda')) {
            $datosNormalizados['moneda'] = strtoupper(trim($this->moneda));
        }

        // Normalizar estado
        if ($this->has('estado')) {
            $datosNormalizados['estado'] = strtolower(trim($this->estado));
        }

        // Convertir valores numéricos
        if ($this->has('monto_cuota')) {
            $datosNormalizados['monto_cuota'] = (float) $this->monto_cuota;
        }

        if ($this->has('interes')) {
            $datosNormalizados['interes'] = $this->interes ? (float) $this->interes : null;
        }

        if ($this->has('mora')) {
            $datosNormalizados['mora'] = $this->mora ? (float) $this->mora : null;
        }

        if ($this->has('numero_cuota')) {
            $datosNormalizados['numero_cuota'] = (int) $this->numero_cuota;
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
        // Validar que el pedido sea de tipo crédito
        if ($this->pedido_id) {
            $pedido = \App\Models\Pedido::find($this->pedido_id);
            if ($pedido && $pedido->tipo_pago !== 'credito') {
                $validator->errors()->add('pedido_id', 'Solo se pueden crear cuotas para pedidos a crédito.');
            }

            // Validar que el número de cuota no exceda el total de cuotas del pedido
            if ($pedido && $this->numero_cuota && $this->numero_cuota > $pedido->cuotas) {
                $validator->errors()->add('numero_cuota', "El número de cuota no puede ser mayor al total de cuotas del pedido ({$pedido->cuotas}).");
            }

            // Validar que no existe ya una cuota con ese número para el pedido
            if ($pedido && $this->numero_cuota) {
                $cuotaExistente = \App\Models\CuotaCredito::where('pedido_id', $pedido->id)
                    ->where('numero_cuota', $this->numero_cuota)
                    ->first();
                    
                if ($cuotaExistente) {
                    $validator->errors()->add('numero_cuota', 'Ya existe una cuota con ese número para este pedido.');
                }
            }
        }

        // Validar que si tiene fecha de pago, el estado debe ser 'pagado'
        if ($this->fecha_pago && $this->estado && $this->estado !== 'pagado') {
            $validator->errors()->add('estado', 'Si se especifica fecha de pago, el estado debe ser "pagado".');
        }

        // Validar que si el estado es 'pagado', debe tener fecha de pago
        if ($this->estado === 'pagado' && !$this->fecha_pago) {
            $validator->errors()->add('fecha_pago', 'Si el estado es "pagado", debe especificar la fecha de pago.');
        }

        // Validar que la fecha de pago no sea anterior a la fecha de vencimiento si está pagada
        if ($this->fecha_pago && $this->fecha_vencimiento) {
            $fechaPago = \Carbon\Carbon::parse($this->fecha_pago);
            $fechaVencimiento = \Carbon\Carbon::parse($this->fecha_vencimiento);
            
            if ($fechaPago->lt($fechaVencimiento->subDays(30))) { // Permitir pago hasta 30 días antes
                $validator->errors()->add('fecha_pago', 'La fecha de pago no puede ser más de 30 días anterior a la fecha de vencimiento.');
            }
        }

        // Validar que si hay mora, el estado debe ser 'atrasado' o 'pagado'
        if ($this->mora && $this->mora > 0 && $this->estado && !in_array($this->estado, ['atrasado', 'pagado'])) {
            $validator->errors()->add('estado', 'Si hay mora, el estado debe ser "atrasado" o "pagado".');
        }

        // Validar que el monto total (cuota + interés + mora) sea coherente
        if ($this->monto_cuota && $this->pedido_id) {
            $pedido = \App\Models\Pedido::find($this->pedido_id);
            if ($pedido && $pedido->monto_cuota) {
                $diferencia = abs($this->monto_cuota - $pedido->monto_cuota);
                $tolerancia = $pedido->monto_cuota * 0.1; // 10% de tolerancia
                
                if ($diferencia > $tolerancia) {
                    $validator->errors()->add('monto_cuota', "El monto de la cuota debe ser similar al monto de cuota del pedido (S/ {$pedido->monto_cuota}).");
                }
            }
        }
    }
} 