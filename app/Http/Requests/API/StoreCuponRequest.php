<?php

declare(strict_types=1);

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCuponRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Asumimos que la autorización se maneja mediante middleware (ej. Sanctum o JWT)
        // y que si llega aquí, el usuario tiene permiso para crear.
        // Puedes añadir lógica de roles/permisos aquí si es necesario.
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:50',
                Rule::unique('cupones', 'codigo')
            ],
            'descuento' => ['required', 'numeric', 'min:0'],
            'tipo' => ['required', 'string', Rule::in(['fijo', 'porcentaje'])],
            'fecha_inicio' => ['required', 'date_format:Y-m-d H:i:s', 'after_or_equal:now'],
            'fecha_fin' => ['required', 'date_format:Y-m-d H:i:s', 'after_or_equal:fecha_inicio'],
            'limite_uso' => ['nullable', 'integer', 'min:1'],
            'activo' => ['sometimes', 'boolean'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('activo') && !is_bool($this->activo)) {
            $this->merge([
                'activo' => filter_var($this->activo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'codigo.unique' => 'El código del cupón ya existe.',
            'tipo.in' => 'El tipo de descuento debe ser "fijo" o "porcentaje".',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio no puede ser anterior a la fecha y hora actual.',
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
        ];
    }
} 