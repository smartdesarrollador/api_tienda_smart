<?php

declare(strict_types=1);

namespace App\Http\Requests\Carrito;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AgregarItemRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'producto_id' => [
                'required',
                'integer',
                'min:1',
                'exists:productos,id'
            ],
            'variacion_id' => [
                'nullable',
                'integer',
                'min:1',
                'exists:variaciones,id'
            ],
            'cantidad' => [
                'required',
                'integer',
                'min:1',
                'max:' . config('carrito.maximo_cantidad_por_item', 99)
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'producto_id.required' => 'El ID del producto es requerido',
            'producto_id.integer' => 'El ID del producto debe ser un número entero',
            'producto_id.min' => 'El ID del producto debe ser mayor a 0',
            'producto_id.exists' => 'El producto seleccionado no existe',
            
            'variacion_id.integer' => 'El ID de la variación debe ser un número entero',
            'variacion_id.min' => 'El ID de la variación debe ser mayor a 0',
            'variacion_id.exists' => 'La variación seleccionada no existe',
            
            'cantidad.required' => 'La cantidad es requerida',
            'cantidad.integer' => 'La cantidad debe ser un número entero',
            'cantidad.min' => 'La cantidad debe ser al menos 1',
            'cantidad.max' => 'La cantidad máxima permitida es :max unidades'
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'producto_id' => (int) $this->producto_id,
            'variacion_id' => $this->variacion_id ? (int) $this->variacion_id : null,
            'cantidad' => (int) $this->cantidad
        ]);
    }
} 