<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateZonaRepartoRequest extends FormRequest
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
        $zonaRepartoId = $this->route('zonaReparto')?->id ?? $this->route('zona_reparto');

        return [
            'nombre' => 'sometimes|string|max:255',
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('zonas_reparto', 'slug')->ignore($zonaRepartoId),
            ],
            'descripcion' => 'sometimes|string|max:1000',
            'costo_envio' => 'sometimes|numeric|min:0|max:999.99',
            'costo_envio_adicional' => 'sometimes|numeric|min:0|max:999.99',
            'tiempo_entrega_min' => 'sometimes|integer|min:1|max:1440',
            'tiempo_entrega_max' => 'sometimes|integer|min:1|max:1440|gte:tiempo_entrega_min',
            'pedido_minimo' => 'sometimes|nullable|numeric|min:0|max:9999.99',
            'radio_cobertura_km' => 'sometimes|nullable|numeric|min:0.1|max:100',
            'coordenadas_centro' => 'sometimes|nullable|string|regex:/^-?\d+\.?\d*,-?\d+\.?\d*$/',
            'poligono_cobertura' => 'sometimes|nullable|json',
            'activo' => 'sometimes|boolean',
            'disponible_24h' => 'sometimes|boolean',
            'orden' => 'sometimes|integer|min:0|max:999',
            'color_mapa' => 'sometimes|nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'observaciones' => 'sometimes|nullable|string|max:2000',
            'distritos_ids' => 'sometimes|array',
            'distritos_ids.*' => 'exists:distritos,id',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'nombre.max' => 'El nombre no puede tener más de 255 caracteres.',
            'slug.unique' => 'Ya existe una zona de reparto con este slug.',
            'costo_envio.numeric' => 'El costo de envío debe ser un número válido.',
            'costo_envio.min' => 'El costo de envío no puede ser negativo.',
            'tiempo_entrega_min.integer' => 'El tiempo mínimo debe ser un número entero.',
            'tiempo_entrega_min.min' => 'El tiempo mínimo debe ser al menos 1 minuto.',
            'tiempo_entrega_max.gte' => 'El tiempo máximo debe ser mayor o igual al tiempo mínimo.',
            'coordenadas_centro.regex' => 'Las coordenadas deben tener el formato: latitud,longitud',
            'poligono_cobertura.json' => 'El polígono de cobertura debe ser un JSON válido.',
            'color_mapa.regex' => 'El color debe ser un código hexadecimal válido (#RRGGBB).',
            'distritos_ids.array' => 'Los distritos deben ser enviados como un array.',
            'distritos_ids.*.exists' => 'Uno o más distritos seleccionados no existen.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'slug' => 'slug',
            'descripcion' => 'descripción',
            'costo_envio' => 'costo de envío',
            'costo_envio_adicional' => 'costo de envío adicional',
            'tiempo_entrega_min' => 'tiempo mínimo de entrega',
            'tiempo_entrega_max' => 'tiempo máximo de entrega',
            'pedido_minimo' => 'pedido mínimo',
            'radio_cobertura_km' => 'radio de cobertura',
            'coordenadas_centro' => 'coordenadas del centro',
            'poligono_cobertura' => 'polígono de cobertura',
            'activo' => 'estado activo',
            'disponible_24h' => 'disponible 24 horas',
            'orden' => 'orden',
            'color_mapa' => 'color en el mapa',
            'observaciones' => 'observaciones',
            'distritos_ids' => 'distritos',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('activo') && is_string($this->activo)) {
            $this->merge([
                'activo' => filter_var($this->activo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        if ($this->has('disponible_24h') && is_string($this->disponible_24h)) {
            $this->merge([
                'disponible_24h' => filter_var($this->disponible_24h, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
} 