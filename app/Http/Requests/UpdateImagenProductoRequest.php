<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateImagenProductoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Autorización manejada por middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'imagen' => [
                'sometimes',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:5120', // 5MB máximo
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            ],
            'alt' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'orden' => [
                'sometimes',
                'integer',
                'min:0',
                'max:999',
            ],
            'principal' => [
                'sometimes',
                'boolean',
            ],
            'tipo' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::in(['miniatura', 'galeria', 'zoom', 'banner', 'detalle']),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'imagen.image' => 'El archivo debe ser una imagen válida.',
            'imagen.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif o webp.',
            'imagen.max' => 'La imagen no debe ser mayor a 5MB.',
            'imagen.dimensions' => 'La imagen debe tener dimensiones mínimas de 100x100px y máximas de 4000x4000px.',
            'alt.max' => 'El texto alternativo no debe exceder 255 caracteres.',
            'orden.integer' => 'El orden debe ser un número entero.',
            'orden.min' => 'El orden debe ser mayor o igual a 0.',
            'orden.max' => 'El orden no debe exceder 999.',
            'principal.boolean' => 'El campo principal debe ser verdadero o falso.',
            'tipo.in' => 'El tipo debe ser uno de: miniatura, galeria, zoom, banner, detalle.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'imagen' => 'imagen',
            'alt' => 'texto alternativo',
            'orden' => 'orden',
            'principal' => 'imagen principal',
            'tipo' => 'tipo de imagen',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Obtener la imagen actual
            $imagenActual = $this->route('imagenProducto');
            
            if (!$imagenActual) {
                return;
            }

            // Validar que no haya más de una imagen principal por producto/variación
            // Solo si se está intentando marcar como principal
            if ($this->filled('principal') && $this->principal && !$imagenActual->principal) {
                $query = \App\Models\ImagenProducto::where('producto_id', $imagenActual->producto_id)
                    ->where('principal', true)
                    ->where('id', '!=', $imagenActual->id);

                if ($imagenActual->variacion_id) {
                    $query->where('variacion_id', $imagenActual->variacion_id);
                } else {
                    $query->whereNull('variacion_id');
                }

                if ($query->exists()) {
                    $validator->errors()->add(
                        'principal',
                        'Ya existe una imagen principal para este producto/variación.'
                    );
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir string 'true'/'false' a boolean para principal
        if ($this->has('principal')) {
            $this->merge([
                'principal' => filter_var($this->principal, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        // Convertir orden a integer si viene como string
        if ($this->has('orden') && is_string($this->orden)) {
            $this->merge([
                'orden' => (int) $this->orden,
            ]);
        }

        // Limpiar y normalizar alt text
        if ($this->has('alt') && is_string($this->alt)) {
            $this->merge([
                'alt' => trim($this->alt),
            ]);
        }
    }
} 