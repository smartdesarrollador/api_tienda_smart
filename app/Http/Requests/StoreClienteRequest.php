<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Usuario relacionado
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:clientes,user_id'],
            
            // Datos personales básicos
            'dni' => ['nullable', 'string', 'max:12', 'regex:/^[0-9]+$/', 'unique:clientes,dni'],
            'telefono' => ['nullable', 'string', 'max:20', 'regex:/^[\+0-9\s\-\(\)]+$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'nombre_completo' => ['nullable', 'string', 'max:255'],
            'apellidos' => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'genero' => ['nullable', Rule::in(['M', 'F', 'O'])],
            
            // Datos financieros
            'limite_credito' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'verificado' => ['nullable', 'boolean'],
            
            // Datos profesionales
            'referido_por' => ['nullable', 'string', 'max:255'],
            'profesion' => ['nullable', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'ingresos_mensuales' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            
            // Datos adicionales (JSON)
            'preferencias' => ['nullable', 'array'],
            'preferencias.categorias_favoritas' => ['nullable', 'array'],
            'preferencias.categorias_favoritas.*' => ['integer', 'exists:categorias,id'],
            'preferencias.notificaciones_email' => ['nullable', 'boolean'],
            'preferencias.notificaciones_sms' => ['nullable', 'boolean'],
            
            'metadata' => ['nullable', 'array'],
            'metadata.fuente_registro' => ['nullable', 'string', 'max:100'],
            'metadata.utm_source' => ['nullable', 'string', 'max:100'],
            'metadata.utm_campaign' => ['nullable', 'string', 'max:100'],
            
            // Estado
            'estado' => ['nullable', Rule::in(['activo', 'inactivo', 'bloqueado'])],
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
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.exists' => 'El usuario especificado no existe.',
            'user_id.unique' => 'Este usuario ya tiene un perfil de cliente asociado.',
            
            'dni.regex' => 'El DNI debe contener solo números.',
            'dni.unique' => 'Este DNI ya está registrado.',
            'dni.max' => 'El DNI no puede tener más de 12 caracteres.',
            
            'telefono.regex' => 'El teléfono tiene un formato inválido.',
            'telefono.max' => 'El teléfono no puede tener más de 20 caracteres.',
            
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'fecha_nacimiento.after' => 'La fecha de nacimiento debe ser posterior a 1900.',
            
            'genero.in' => 'El género debe ser M (Masculino), F (Femenino) o O (Otro).',
            
            'limite_credito.min' => 'El límite de crédito no puede ser negativo.',
            'limite_credito.max' => 'El límite de crédito no puede exceder 999,999.99.',
            
            'ingresos_mensuales.min' => 'Los ingresos mensuales no pueden ser negativos.',
            'ingresos_mensuales.max' => 'Los ingresos mensuales no pueden exceder 999,999.99.',
            
            'estado.in' => 'El estado debe ser: activo, inactivo o bloqueado.',
            
            'preferencias.categorias_favoritas.*.exists' => 'Una de las categorías favoritas no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'usuario',
            'dni' => 'DNI',
            'telefono' => 'teléfono',
            'direccion' => 'dirección',
            'nombre_completo' => 'nombre completo',
            'apellidos' => 'apellidos',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'genero' => 'género',
            'limite_credito' => 'límite de crédito',
            'verificado' => 'verificado',
            'referido_por' => 'referido por',
            'profesion' => 'profesión',
            'empresa' => 'empresa',
            'ingresos_mensuales' => 'ingresos mensuales',
            'preferencias' => 'preferencias',
            'metadata' => 'metadata',
            'estado' => 'estado',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Limpiar y formatear datos antes de validación
        if ($this->has('dni') && $this->dni) {
            $this->merge([
                'dni' => preg_replace('/[^0-9]/', '', $this->dni)
            ]);
        }

        if ($this->has('telefono') && $this->telefono) {
            $this->merge([
                'telefono' => preg_replace('/[^0-9+]/', '', $this->telefono)
            ]);
        }

        // Asegurar valores por defecto
        $this->merge([
            'limite_credito' => $this->limite_credito ?? 0,
            'verificado' => $this->verificado ?? false,
            'estado' => $this->estado ?? 'activo',
        ]);
    }
}
