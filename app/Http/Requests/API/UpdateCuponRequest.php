<?php

declare(strict_types=1);

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCuponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cuponId = $this->route('cupon') instanceof \App\Models\Cupon ? $this->route('cupon')->id : $this->route('cupon');

        return [
            'codigo' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('cupones', 'codigo')->ignore($cuponId),
            ],
            'descuento' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tipo' => ['sometimes', 'required', 'string', Rule::in(['fijo', 'porcentaje'])],
            'fecha_inicio' => ['sometimes', 'required', 'date_format:Y-m-d H:i:s'],
            'fecha_fin' => [
                'sometimes',
                'required',
                'date_format:Y-m-d H:i:s',
                function ($attribute, $value, $fail) {
                    $fechaInicio = $this->input('fecha_inicio', $this->route('cupon')->fecha_inicio);
                    if (\Carbon\Carbon::parse($value)->lt(\Carbon\Carbon::parse($fechaInicio))) {
                        $fail('La fecha de fin no puede ser anterior a la fecha de inicio.');
                    }
                },
            ],
            'limite_uso' => ['nullable', 'integer', 'min:1'],
            'activo' => ['sometimes', 'boolean'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('activo') && !is_bool($this->activo)) {
            $this->merge([
                'activo' => filter_var($this->activo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->route('cupon')->activo,
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'codigo.unique' => 'El código del cupón ya existe.',
            'tipo.in' => 'El tipo de descuento debe ser "fijo" o "porcentaje".',
        ];
    }
} 