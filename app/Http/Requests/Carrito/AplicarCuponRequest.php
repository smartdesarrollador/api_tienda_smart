<?php

declare(strict_types=1);

namespace App\Http\Requests\Carrito;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AplicarCuponRequest extends FormRequest
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
            'codigo' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[A-Za-z0-9\-_]+$/'
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
            'codigo.required' => 'El código del cupón es requerido',
            'codigo.string' => 'El código del cupón debe ser una cadena de texto',
            'codigo.min' => 'El código del cupón debe tener al menos :min caracteres',
            'codigo.max' => 'El código del cupón no puede tener más de :max caracteres',
            'codigo.regex' => 'El código del cupón solo puede contener letras, números, guiones y guiones bajos'
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
            'codigo' => strtoupper(trim($this->codigo ?? ''))
        ]);
    }
} 