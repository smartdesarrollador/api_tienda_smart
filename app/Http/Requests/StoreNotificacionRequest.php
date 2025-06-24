<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificacionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el middleware
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
                'exists:users,id'
            ],
            'titulo' => [
                'required',
                'string',
                'min:5',
                'max:255'
            ],
            'mensaje' => [
                'required',
                'string',
                'min:10',
                'max:1000'
            ],
            'tipo' => [
                'nullable',
                'string',
                Rule::in([
                    'pedido',
                    'pago', 
                    'promocion',
                    'stock',
                    'sistema',
                    'bienvenida',
                    'recordatorio',
                    'credito',
                    'admin',
                    'inventario',
                    'general'
                ])
            ],
            'leido' => [
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
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.exists' => 'El usuario especificado no existe.',
            'titulo.required' => 'El título es obligatorio.',
            'titulo.min' => 'El título debe tener al menos 5 caracteres.',
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'mensaje.required' => 'El mensaje es obligatorio.',
            'mensaje.min' => 'El mensaje debe tener al menos 10 caracteres.',
            'mensaje.max' => 'El mensaje no puede exceder 1000 caracteres.',
            'tipo.in' => 'El tipo de notificación no es válido.',
            'leido.boolean' => 'El estado de lectura debe ser verdadero o falso.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalizar datos
        if ($this->has('titulo')) {
            $this->merge([
                'titulo' => trim($this->titulo)
            ]);
        }

        if ($this->has('mensaje')) {
            $this->merge([
                'mensaje' => trim($this->mensaje)
            ]);
        }

        if ($this->has('tipo')) {
            $this->merge([
                'tipo' => strtolower(trim($this->tipo))
            ]);
        }

        // Establecer valores por defecto
        if (!$this->has('leido')) {
            $this->merge([
                'leido' => false
            ]);
        }

        if (!$this->has('tipo') || empty($this->tipo)) {
            $this->merge([
                'tipo' => 'general'
            ]);
        }
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        // Asegurar que el tipo esté en minúsculas
        if (isset($validated['tipo'])) {
            $validated['tipo'] = strtolower($validated['tipo']);
        }

        return $validated;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validación personalizada: verificar que el usuario existe y está activo
            if ($this->has('user_id')) {
                $user = \App\Models\User::find($this->user_id);
                if (!$user) {
                    $validator->errors()->add('user_id', 'El usuario especificado no existe.');
                }
            }

            // Validación de contenido: no permitir títulos y mensajes idénticos
            if ($this->has('titulo') && $this->has('mensaje')) {
                if (strtolower(trim($this->titulo)) === strtolower(trim($this->mensaje))) {
                    $validator->errors()->add('mensaje', 'El mensaje no puede ser idéntico al título.');
                }
            }

            // Validación de spam: detectar contenido repetitivo
            if ($this->has('mensaje')) {
                $mensaje = strtolower(trim($this->mensaje));
                $palabras = explode(' ', $mensaje);
                
                if (count($palabras) > 3) {
                    $palabrasUnicas = array_unique($palabras);
                    $porcentajeRepeticion = (count($palabras) - count($palabrasUnicas)) / count($palabras) * 100;
                    
                    if ($porcentajeRepeticion > 50) {
                        $validator->errors()->add('mensaje', 'El mensaje parece contener contenido repetitivo.');
                    }
                }
            }

            // Validación de límites: no más de 10 notificaciones no leídas por usuario
            if ($this->has('user_id')) {
                $notificacionesNoLeidas = \App\Models\Notificacion::where('user_id', $this->user_id)
                    ->where('leido', false)
                    ->count();
                
                if ($notificacionesNoLeidas >= 10) {
                    $validator->errors()->add('user_id', 'El usuario tiene demasiadas notificaciones no leídas. Máximo 10 permitidas.');
                }
            }
        });
    }
} 