<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDireccionRequest extends FormRequest
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
        $direccionId = $this->route('direccion')?->id;

        return [
            'direccion' => [
                'sometimes',
                'required',
                'string',
                'min:10',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\.\,\-\#\/]+$/'
            ],
            'referencia' => [
                'sometimes',
                'nullable',
                'string',
                'max:255'
            ],
            'distrito' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/'
            ],
            'provincia' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/'
            ],
            'departamento' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/'
            ],
            'pais' => [
                'sometimes',
                'nullable',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/'
            ],
            'codigo_postal' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9A-Za-z\-\s]+$/'
            ],
            'predeterminada' => [
                'sometimes',
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
        $data = [];

        if ($this->has('direccion')) {
            $data['direccion'] = $this->normalizeText($this->direccion);
        }

        if ($this->has('referencia')) {
            $data['referencia'] = $this->normalizeText($this->referencia);
        }

        if ($this->has('distrito')) {
            $data['distrito'] = $this->normalizeText($this->distrito);
        }

        if ($this->has('provincia')) {
            $data['provincia'] = $this->normalizeText($this->provincia);
        }

        if ($this->has('departamento')) {
            $data['departamento'] = $this->normalizeText($this->departamento);
        }

        if ($this->has('pais')) {
            $data['pais'] = $this->normalizeText($this->pais) ?: 'Perú';
        }

        if ($this->has('codigo_postal')) {
            $data['codigo_postal'] = $this->normalizeText($this->codigo_postal);
        }

        if ($this->has('predeterminada')) {
            $data['predeterminada'] = $this->boolean('predeterminada');
        }

        $this->merge($data);
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
        $locationFields = ['distrito', 'provincia', 'departamento', 'pais'];
        foreach ($locationFields as $field) {
            if ($this->has($field)) {
                $text = ucwords(strtolower($text));
                break;
            }
        }

        return $text;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $direccion = $this->route('direccion');
            
            if (!$direccion) {
                return;
            }

            // Validar que no exista una dirección idéntica para el mismo usuario (excluyendo la actual)
            if ($this->has('direccion') || $this->has('distrito') || $this->has('provincia') || $this->has('departamento')) {
                $direccionTexto = $this->direccion ?? $direccion->direccion;
                $distrito = $this->distrito ?? $direccion->distrito;
                $provincia = $this->provincia ?? $direccion->provincia;
                $departamento = $this->departamento ?? $direccion->departamento;

                $direccionExistente = \App\Models\Direccion::where('user_id', $direccion->user_id)
                    ->where('id', '!=', $direccion->id)
                    ->where('direccion', $direccionTexto)
                    ->where('distrito', $distrito)
                    ->where('provincia', $provincia)
                    ->where('departamento', $departamento)
                    ->exists();

                if ($direccionExistente) {
                    $validator->errors()->add('direccion', 'Ya existe una dirección idéntica para este usuario.');
                }
            }

            // Validar código postal según el país
            if ($this->has('codigo_postal') || $this->has('pais')) {
                $codigoPostal = $this->codigo_postal ?? $direccion->codigo_postal;
                $pais = $this->pais ?? $direccion->pais;

                if ($codigoPostal && $pais) {
                    if (strtolower($pais) === 'perú' || strtolower($pais) === 'peru') {
                        if (!preg_match('/^\d{5}$/', $codigoPostal)) {
                            $validator->errors()->add('codigo_postal', 'El código postal para Perú debe tener 5 dígitos.');
                        }
                    }
                }
            }

            // Validar que no se pueda desmarcar la única dirección predeterminada
            if ($this->has('predeterminada') && $this->predeterminada === false && $direccion->predeterminada) {
                $otrasPredet = \App\Models\Direccion::where('user_id', $direccion->user_id)
                    ->where('id', '!=', $direccion->id)
                    ->where('predeterminada', true)
                    ->count();

                if ($otrasPredet === 0) {
                    $validator->errors()->add('predeterminada', 'No se puede desmarcar la única dirección predeterminada. Marque otra como predeterminada primero.');
                }
            }

            // Validar que los campos requeridos no se envíen vacíos si se incluyen
            $camposRequeridos = ['direccion', 'distrito', 'provincia', 'departamento'];
            foreach ($camposRequeridos as $campo) {
                if ($this->has($campo) && empty($this->$campo)) {
                    $validator->errors()->add($campo, "El campo {$campo} no puede estar vacío.");
                }
            }
        });
    }

    /**
     * Get the validated data from the request with only the fields that were sent.
     */
    public function validatedWithPresent(): array
    {
        $validated = $this->validated();
        $present = [];

        foreach ($validated as $key => $value) {
            if ($this->has($key)) {
                $present[$key] = $value;
            }
        }

        return $present;
    }
} 