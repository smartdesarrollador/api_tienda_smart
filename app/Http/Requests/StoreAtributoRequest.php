<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StoreAtributoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Se cambiará a una lógica de autorización basada en roles/permisos si es necesario
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
            'nombre' => ['required', 'string', 'max:255', 'unique:atributos,nombre'],
            'tipo' => ['required', 'string', Rule::in(['texto', 'color', 'numero', 'tamaño', 'booleano'])],
            'filtrable' => ['sometimes', 'boolean'],
            'visible' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('nombre')) {
            $this->merge([
                'slug' => Str::slug($this->nombre),
            ]);
        }
        if ($this->has('filtrable') && !is_bool($this->filtrable)) {
            $this->merge([
                'filtrable' => filter_var($this->filtrable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
        if ($this->has('visible') && !is_bool($this->visible)) {
            $this->merge([
                'visible' => filter_var($this->visible, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del atributo es obligatorio.',
            'nombre.unique' => 'El nombre del atributo ya existe.',
            'nombre.max' => 'El nombre no puede exceder los 255 caracteres.',
            'tipo.required' => 'El tipo de atributo es obligatorio.',
            'tipo.in' => 'El tipo de atributo seleccionado no es válido. Valores permitidos: texto, color, numero, tamaño, booleano.',
            'filtrable.boolean' => 'El campo filtrable debe ser verdadero o falso.',
            'visible.boolean' => 'El campo visible debe ser verdadero o falso.',
        ];
    }
} 