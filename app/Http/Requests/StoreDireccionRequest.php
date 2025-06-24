<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDireccionRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],
            'direccion' => [
                'required',
                'string',
                'min:10',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\.\,\-\#\/]+$/'
            ],
            'referencia' => [
                'nullable',
                'string',
                'max:255'
            ],
            'distrito' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/'
            ],
            'provincia' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/'
            ],
            'departamento' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/'
            ],
            'pais' => [
                'nullable',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/'
            ],
            'codigo_postal' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9A-Za-z\-\s]+$/'
            ],
            'predeterminada' => [
                'nullable',
                'boolean'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.exists' => 'El usuario seleccionado no existe o ha sido eliminado.',
            
            'direccion.required' => 'La dirección es obligatoria.',
            'direccion.min' => 'La dirección debe tener al menos 10 caracteres.',
            'direccion.max' => 'La dirección no puede exceder 255 caracteres.',
            'direccion.regex' => 'La dirección contiene caracteres no válidos.',
            
            'referencia.max' => 'La referencia no puede exceder 255 caracteres.',
            
            'distrito.required' => 'El distrito es obligatorio.',
            'distrito.min' => 'El distrito debe tener al menos 2 caracteres.',
            'distrito.max' => 'El distrito no puede exceder 100 caracteres.',
            'distrito.regex' => 'El distrito solo puede contener letras, espacios y guiones.',
            
            'provincia.required' => 'La provincia es obligatoria.',
            'provincia.min' => 'La provincia debe tener al menos 2 caracteres.',
            'provincia.max' => 'La provincia no puede exceder 100 caracteres.',
            'provincia.regex' => 'La provincia solo puede contener letras, espacios y guiones.',
            
            'departamento.required' => 'El departamento es obligatorio.',
            'departamento.min' => 'El departamento debe tener al menos 2 caracteres.',
            'departamento.max' => 'El departamento no puede exceder 100 caracteres.',
            'departamento.regex' => 'El departamento solo puede contener letras, espacios y guiones.',
            
            'pais.min' => 'El país debe tener al menos 2 caracteres.',
            'pais.max' => 'El país no puede exceder 100 caracteres.',
            'pais.regex' => 'El país solo puede contener letras, espacios y guiones.',
            
            'codigo_postal.max' => 'El código postal no puede exceder 20 caracteres.',
            'codigo_postal.regex' => 'El código postal contiene caracteres no válidos.',
            
            'predeterminada.boolean' => 'El campo predeterminada debe ser verdadero o falso.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'usuario',
            'direccion' => 'dirección',
            'referencia' => 'referencia',
            'distrito' => 'distrito',
            'provincia' => 'provincia',
            'departamento' => 'departamento',
            'pais' => 'país',
            'codigo_postal' => 'código postal',
            'predeterminada' => 'predeterminada'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'direccion' => $this->normalizeText($this->direccion),
            'referencia' => $this->normalizeText($this->referencia),
            'distrito' => $this->normalizeText($this->distrito),
            'provincia' => $this->normalizeText($this->provincia),
            'departamento' => $this->normalizeText($this->departamento),
            'pais' => $this->normalizeText($this->pais) ?: 'Perú',
            'codigo_postal' => $this->normalizeText($this->codigo_postal),
            'predeterminada' => $this->boolean('predeterminada', false)
        ]);
    }

    /**
     * Normalize text fields
     */
    private function normalizeText(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        // Trim whitespace and normalize spaces
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Capitalize first letter of each word for location fields
        if (in_array($this->getRouteKey(), ['distrito', 'provincia', 'departamento', 'pais'])) {
            $text = ucwords(strtolower($text));
        }

        return $text;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que el usuario sea un cliente
            if ($this->user_id) {
                $user = \App\Models\User::find($this->user_id);
                if ($user && $user->rol !== 'cliente') {
                    $validator->errors()->add('user_id', 'Solo se pueden crear direcciones para usuarios con rol de cliente.');
                }
            }

            // Validar límite de direcciones por usuario (máximo 10)
            if ($this->user_id) {
                $totalDirecciones = \App\Models\Direccion::where('user_id', $this->user_id)->count();
                if ($totalDirecciones >= 10) {
                    $validator->errors()->add('user_id', 'El usuario ya tiene el máximo de 10 direcciones permitidas.');
                }
            }

            // Validar que no exista una dirección idéntica para el mismo usuario
            if ($this->user_id && $this->direccion && $this->distrito) {
                $direccionExistente = \App\Models\Direccion::where('user_id', $this->user_id)
                    ->where('direccion', $this->direccion)
                    ->where('distrito', $this->distrito)
                    ->where('provincia', $this->provincia)
                    ->where('departamento', $this->departamento)
                    ->exists();

                if ($direccionExistente) {
                    $validator->errors()->add('direccion', 'Ya existe una dirección idéntica para este usuario.');
                }
            }

            // Validar código postal según el país
            if ($this->codigo_postal && $this->pais) {
                if (strtolower($this->pais) === 'perú' || strtolower($this->pais) === 'peru') {
                    if (!preg_match('/^\d{5}$/', $this->codigo_postal)) {
                        $validator->errors()->add('codigo_postal', 'El código postal para Perú debe tener 5 dígitos.');
                    }
                }
            }
        });
    }
} 