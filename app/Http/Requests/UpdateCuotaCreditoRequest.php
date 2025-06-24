<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class UpdateCuotaCreditoRequest extends FormRequest
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
        $cuotaCredito = $this->route('cuotaCredito');
        
        return [
            // Número de cuota (solo si no está pagada)
            'numero_cuota' => [
                'sometimes',
                'integer',
                'min:1',
                'max:24',
                function ($attribute, $value, $fail) use ($cuotaCredito) {
                    if ($cuotaCredito && $cuotaCredito->estado === 'pagado') {
                        $fail('No se puede modificar el número de cuota de una cuota ya pagada.');
                    }
                }
            ],

            // Monto de la cuota (solo si no está pagada)
            'monto_cuota' => [
                'sometimes',
                'numeric',
                'min:0.01',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) use ($cuotaCredito) {
                    if ($cuotaCredito && $cuotaCredito->estado === 'pagado') {
                        $fail('No se puede modificar el monto de una cuota ya pagada.');
                    }
                }
            ],

            // Interés de la cuota
            'interes' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99999.99',
                'regex:/^\d+(\.\d{1,2})?$/'
            ],

            // Mora de la cuota
            'mora' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99999.99',
                'regex:/^\d+(\.\d{1,2})?$/'
            ],

            // Fecha de vencimiento (solo si no está pagada)
            'fecha_vencimiento' => [
                'sometimes',
                'date',
                'after:2020-01-01',
                'before:' . now()->addYears(5)->format('Y-m-d'),
                function ($attribute, $value, $fail) use ($cuotaCredito) {
                    if ($cuotaCredito && $cuotaCredito->estado === 'pagado') {
                        $fail('No se puede modificar la fecha de vencimiento de una cuota ya pagada.');
                    }
                }
            ],

            // Fecha de pago
            'fecha_pago' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:today',
                'after:2020-01-01'
            ],

            // Estado de la cuota
            'estado' => [
                'sometimes',
                'string',
                'in:pendiente,pagado,atrasado,condonado'
            ],

            // Moneda (solo si no está pagada)
            'moneda' => [
                'sometimes',
                'string',
                'in:PEN,USD,EUR',
                'size:3',
                function ($attribute, $value, $fail) use ($cuotaCredito) {
                    if ($cuotaCredito && $cuotaCredito->estado === 'pagado') {
                        $fail('No se puede modificar la moneda de una cuota ya pagada.');
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
            // Número de cuota
            'numero_cuota.integer' => 'El número de cuota debe ser un entero.',
            'numero_cuota.min' => 'El número de cuota debe ser mayor a 0.',
            'numero_cuota.max' => 'El número de cuota no puede ser mayor a 24.',

            // Monto de cuota
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
            'fecha_vencimiento.date' => 'La fecha de vencimiento debe ser una fecha válida.',
            'fecha_vencimiento.after' => 'La fecha de vencimiento debe ser posterior al 1 de enero de 2020.',
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

        if (!empty($datosNormalizados)) {
            $this->merge($datosNormalizados);
        }
    }

    /**
     * Validaciones adicionales de lógica de negocio.
     */
    private function validarLogicaNegocio($validator): void
    {
        $cuotaCredito = $this->route('cuotaCredito');
        
        if (!$cuotaCredito) {
            return;
        }

        // Validar que si se cambia el número de cuota, no exista ya otra con ese número
        if ($this->has('numero_cuota') && $this->numero_cuota !== $cuotaCredito->numero_cuota) {
            $cuotaExistente = \App\Models\CuotaCredito::where('pedido_id', $cuotaCredito->pedido_id)
                ->where('numero_cuota', $this->numero_cuota)
                ->where('id', '!=', $cuotaCredito->id)
                ->first();
                
            if ($cuotaExistente) {
                $validator->errors()->add('numero_cuota', 'Ya existe otra cuota con ese número para este pedido.');
            }

            // Validar que el nuevo número no exceda el total de cuotas del pedido
            $pedido = $cuotaCredito->pedido;
            if ($pedido && $this->numero_cuota > $pedido->cuotas) {
                $validator->errors()->add('numero_cuota', "El número de cuota no puede ser mayor al total de cuotas del pedido ({$pedido->cuotas}).");
            }
        }

        // Validar transiciones de estado válidas
        if ($this->has('estado') && $this->estado !== $cuotaCredito->estado) {
            $transicionesValidas = [
                'pendiente' => ['pagado', 'atrasado', 'condonado'],
                'atrasado' => ['pagado', 'condonado'],
                'pagado' => [], // No se puede cambiar desde pagado
                'condonado' => ['pendiente', 'atrasado'] // Se puede reactivar
            ];

            $estadosPermitidos = $transicionesValidas[$cuotaCredito->estado] ?? [];
            
            if (!in_array($this->estado, $estadosPermitidos)) {
                $validator->errors()->add('estado', "No se puede cambiar el estado de '{$cuotaCredito->estado}' a '{$this->estado}'.");
            }
        }

        // Validar que si se marca como pagada, debe tener fecha de pago
        if ($this->has('estado') && $this->estado === 'pagado') {
            $fechaPago = $this->has('fecha_pago') ? $this->fecha_pago : $cuotaCredito->fecha_pago;
            if (!$fechaPago) {
                $validator->errors()->add('fecha_pago', 'Si el estado es "pagado", debe especificar la fecha de pago.');
            }
        }

        // Validar que si tiene fecha de pago, el estado debe ser 'pagado'
        if ($this->has('fecha_pago') && $this->fecha_pago) {
            $estado = $this->has('estado') ? $this->estado : $cuotaCredito->estado;
            if ($estado !== 'pagado') {
                $validator->errors()->add('estado', 'Si se especifica fecha de pago, el estado debe ser "pagado".');
            }
        }

        // Validar que la fecha de pago no sea muy anterior a la fecha de vencimiento
        if ($this->has('fecha_pago') && $this->fecha_pago) {
            $fechaVencimiento = $this->has('fecha_vencimiento') ? $this->fecha_vencimiento : $cuotaCredito->fecha_vencimiento;
            
            $fechaPago = \Carbon\Carbon::parse($this->fecha_pago);
            $fechaVenc = \Carbon\Carbon::parse($fechaVencimiento);
            
            if ($fechaPago->lt($fechaVenc->subDays(30))) { // Permitir pago hasta 30 días antes
                $validator->errors()->add('fecha_pago', 'La fecha de pago no puede ser más de 30 días anterior a la fecha de vencimiento.');
            }
        }

        // Validar que si hay mora, el estado debe ser 'atrasado' o 'pagado'
        if ($this->has('mora') && $this->mora > 0) {
            $estado = $this->has('estado') ? $this->estado : $cuotaCredito->estado;
            if (!in_array($estado, ['atrasado', 'pagado'])) {
                $validator->errors()->add('estado', 'Si hay mora, el estado debe ser "atrasado" o "pagado".');
            }
        }

        // Validar que el monto de la cuota sea coherente con el pedido
        if ($this->has('monto_cuota') && $this->monto_cuota) {
            $pedido = $cuotaCredito->pedido;
            if ($pedido && $pedido->monto_cuota) {
                $diferencia = abs($this->monto_cuota - $pedido->monto_cuota);
                $tolerancia = $pedido->monto_cuota * 0.1; // 10% de tolerancia
                
                if ($diferencia > $tolerancia) {
                    $validator->errors()->add('monto_cuota', "El monto de la cuota debe ser similar al monto de cuota del pedido (S/ {$pedido->monto_cuota}).");
                }
            }
        }

        // Validar que no se puede eliminar la fecha de pago si ya está pagada
        if ($this->has('fecha_pago') && !$this->fecha_pago && $cuotaCredito->estado === 'pagado') {
            $validator->errors()->add('fecha_pago', 'No se puede eliminar la fecha de pago de una cuota ya pagada.');
        }

        // Validar que si se cambia a estado 'condonado', debe tener una justificación (en observaciones futuras)
        if ($this->has('estado') && $this->estado === 'condonado' && $cuotaCredito->estado !== 'condonado') {
            // Esta validación se puede extender cuando se agregue el campo observaciones
            Log::info("Cuota condonada", [
                'cuota_id' => $cuotaCredito->id,
                'pedido_id' => $cuotaCredito->pedido_id,
                'monto_condonado' => $cuotaCredito->monto_cuota
            ]);
        }
    }
} 