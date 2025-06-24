<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDireccionValidadaRequest extends FormRequest
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
            'direccion_id' => 'sometimes|exists:direcciones,id',
            'zona_reparto_id' => 'sometimes|nullable|exists:zonas_reparto,id',
            'latitud' => 'sometimes|numeric|between:-90,90',
            'longitud' => 'sometimes|numeric|between:-180,180',
            'distancia_tienda_km' => 'sometimes|nullable|numeric|min:0|max:1000',
            'en_zona_cobertura' => 'sometimes|boolean',
            'costo_envio_calculado' => 'sometimes|nullable|numeric|min:0|max:999.99',
            'tiempo_entrega_estimado' => 'sometimes|nullable|integer|min:1|max:1440',
            'observaciones_validacion' => 'sometimes|nullable|string|max:1000',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'direccion_id.exists' => 'La dirección seleccionada no existe.',
            'zona_reparto_id.exists' => 'La zona de reparto seleccionada no existe.',
            'latitud.numeric' => 'La latitud debe ser un número válido.',
            'latitud.between' => 'La latitud debe estar entre -90 y 90 grados.',
            'longitud.numeric' => 'La longitud debe ser un número válido.',
            'longitud.between' => 'La longitud debe estar entre -180 y 180 grados.',
            'distancia_tienda_km.numeric' => 'La distancia debe ser un número válido.',
            'distancia_tienda_km.min' => 'La distancia no puede ser negativa.',
            'en_zona_cobertura.boolean' => 'El campo de cobertura debe ser verdadero o falso.',
            'costo_envio_calculado.numeric' => 'El costo de envío debe ser un número válido.',
            'costo_envio_calculado.min' => 'El costo de envío no puede ser negativo.',
            'tiempo_entrega_estimado.integer' => 'El tiempo de entrega debe ser un número entero.',
            'tiempo_entrega_estimado.min' => 'El tiempo de entrega debe ser al menos 1 minuto.',
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
            'zona_reparto_id' => 'zona de reparto',
            'latitud' => 'latitud',
            'longitud' => 'longitud',
            'distancia_tienda_km' => 'distancia a la tienda',
            'en_zona_cobertura' => 'en zona de cobertura',
            'costo_envio_calculado' => 'costo de envío calculado',
            'tiempo_entrega_estimado' => 'tiempo de entrega estimado',
            'observaciones_validacion' => 'observaciones de validación',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('en_zona_cobertura') && is_string($this->en_zona_cobertura)) {
            $this->merge([
                'en_zona_cobertura' => filter_var($this->en_zona_cobertura, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        // Convertir strings vacíos a null para campos opcionales numéricos
        if ($this->has('zona_reparto_id') && $this->zona_reparto_id === '') {
            $this->merge(['zona_reparto_id' => null]);
        }

        if ($this->has('distancia_tienda_km') && $this->distancia_tienda_km === '') {
            $this->merge(['distancia_tienda_km' => null]);
        }

        if ($this->has('costo_envio_calculado') && $this->costo_envio_calculado === '') {
            $this->merge(['costo_envio_calculado' => null]);
        }

        if ($this->has('tiempo_entrega_estimado') && $this->tiempo_entrega_estimado === '') {
            $this->merge(['tiempo_entrega_estimado' => null]);
        }
    }
} 