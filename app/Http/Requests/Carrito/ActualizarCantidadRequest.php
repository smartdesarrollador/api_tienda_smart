<?php

declare(strict_types=1);

namespace App\Http\Requests\Carrito;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ActualizarCantidadRequest extends FormRequest
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
            'item_id' => [
                'required',
                'string',
                'min:1'
            ],
            'cantidad' => [
                'required',
                'integer',
                'min:0',
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
            'item_id.required' => 'El ID del item es requerido',
            'item_id.string' => 'El ID del item debe ser una cadena de texto',
            'item_id.min' => 'El ID del item no puede estar vacío',
            
            'cantidad.required' => 'La cantidad es requerida',
            'cantidad.integer' => 'La cantidad debe ser un número entero',
            'cantidad.min' => 'La cantidad no puede ser negativa',
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
            'item_id' => (string) $this->item_id,
            'cantidad' => (int) $this->cantidad
        ]);
    }
} 