<?php

declare(strict_types=1);

namespace App\Http\Requests\Carrito;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CalcularEnvioRequest extends FormRequest
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
            'departamento' => [
                'required',
                'string',
                'min:2',
                'max:50'
            ],
            'provincia' => [
                'required',
                'string',
                'min:2',
                'max:50'
            ],
            'distrito' => [
                'required',
                'string',
                'min:2',
                'max:50'
            ],
            'codigo_postal' => [
                'nullable',
                'string',
                'regex:/^[0-9]{5}$/'
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
            'departamento.required' => 'El departamento es requerido',
            'departamento.string' => 'El departamento debe ser una cadena de texto',
            'departamento.min' => 'El departamento debe tener al menos :min caracteres',
            'departamento.max' => 'El departamento no puede tener más de :max caracteres',
            
            'provincia.required' => 'La provincia es requerida',
            'provincia.string' => 'La provincia debe ser una cadena de texto',
            'provincia.min' => 'La provincia debe tener al menos :min caracteres',
            'provincia.max' => 'La provincia no puede tener más de :max caracteres',
            
            'distrito.required' => 'El distrito es requerido',
            'distrito.string' => 'El distrito debe ser una cadena de texto',
            'distrito.min' => 'El distrito debe tener al menos :min caracteres',
            'distrito.max' => 'El distrito no puede tener más de :max caracteres',
            
            'codigo_postal.string' => 'El código postal debe ser una cadena de texto',
            'codigo_postal.regex' => 'El código postal debe tener 5 dígitos'
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
            'departamento' => trim($this->departamento ?? ''),
            'provincia' => trim($this->provincia ?? ''),
            'distrito' => trim($this->distrito ?? ''),
            'codigo_postal' => $this->codigo_postal ? trim($this->codigo_postal) : null
        ]);
    }
} 