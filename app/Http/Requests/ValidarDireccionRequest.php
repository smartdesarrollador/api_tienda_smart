<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidarDireccionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Ajustar según sistema de permisos
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'direccion_id' => 'required|exists:direcciones,id',
            'latitud' => 'sometimes|numeric|between:-90,90',
            'longitud' => 'sometimes|numeric|between:-180,180',
            'forzar_zona_id' => 'sometimes|exists:zonas_reparto,id',
            'observaciones_validacion' => 'sometimes|string|max:1000',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'direccion_id.required' => 'La dirección es obligatoria.',
            'direccion_id.exists' => 'La dirección seleccionada no existe.',
            'latitud.numeric' => 'La latitud debe ser un número válido.',
            'latitud.between' => 'La latitud debe estar entre -90 y 90 grados.',
            'longitud.numeric' => 'La longitud debe ser un número válido.',
            'longitud.between' => 'La longitud debe estar entre -180 y 180 grados.',
            'forzar_zona_id.exists' => 'La zona de reparto forzada no existe.',
            'observaciones_validacion.max' => 'Las observaciones no pueden tener más de 1000 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'direccion_id' => 'dirección',
            'latitud' => 'latitud',
            'longitud' => 'longitud',
            'forzar_zona_id' => 'zona de reparto forzada',
            'observaciones_validacion' => 'observaciones de validación',
        ];
    }
} 