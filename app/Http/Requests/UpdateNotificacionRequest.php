<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificacionRequest extends FormRequest
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
        $notificacion = $this->route('notificacion');

        return [
            'user_id' => [
                'sometimes',
                'integer',
                'exists:users,id'
            ],
            'titulo' => [
                'sometimes',
                'string',
                'min:5',
                'max:255'
            ],
            'mensaje' => [
                'sometimes',
                'string',
                'min:10',
                'max:1000'
            ],
            'tipo' => [
                'sometimes',
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
            'user_id.exists' => 'El usuario especificado no existe.',
            'titulo.min' => 'El título debe tener al menos 5 caracteres.',
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
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
        // Normalizar datos solo si están presentes
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
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        // Asegurar que el tipo esté en minúsculas si está presente
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
            $notificacion = $this->route('notificacion');

            // Validación personalizada: verificar que el usuario existe si se está cambiando
            if ($this->has('user_id') && $this->user_id !== $notificacion->user_id) {
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

            // Validación de contenido con datos existentes
            $titulo = $this->has('titulo') ? $this->titulo : $notificacion->titulo;
            $mensaje = $this->has('mensaje') ? $this->mensaje : $notificacion->mensaje;

            if (strtolower(trim($titulo)) === strtolower(trim($mensaje))) {
                $validator->errors()->add('mensaje', 'El mensaje no puede ser idéntico al título.');
            }

            // Validación de spam: detectar contenido repetitivo en mensaje actualizado
            if ($this->has('mensaje')) {
                $mensajeNormalizado = strtolower(trim($this->mensaje));
                $palabras = explode(' ', $mensajeNormalizado);
                
                if (count($palabras) > 3) {
                    $palabrasUnicas = array_unique($palabras);
                    $porcentajeRepeticion = (count($palabras) - count($palabrasUnicas)) / count($palabras) * 100;
                    
                    if ($porcentajeRepeticion > 50) {
                        $validator->errors()->add('mensaje', 'El mensaje parece contener contenido repetitivo.');
                    }
                }
            }

            // Validación de límites: si se cambia el usuario, verificar límites
            if ($this->has('user_id') && $this->user_id !== $notificacion->user_id) {
                $notificacionesNoLeidas = \App\Models\Notificacion::where('user_id', $this->user_id)
                    ->where('leido', false)
                    ->where('id', '!=', $notificacion->id)
                    ->count();
                
                if ($notificacionesNoLeidas >= 10) {
                    $validator->errors()->add('user_id', 'El usuario objetivo tiene demasiadas notificaciones no leídas. Máximo 10 permitidas.');
                }
            }

            // Validación de lógica de negocio: notificaciones críticas no se pueden marcar como no leídas fácilmente
            if ($this->has('leido') && $this->leido === false) {
                $tiposCriticos = ['pago', 'stock', 'admin'];
                if (in_array($notificacion->tipo, $tiposCriticos) && $notificacion->leido === true) {
                    // Permitir solo si es un administrador
                    $user = auth()->user();
                    if (!$user || $user->rol !== 'administrador') {
                        $validator->errors()->add('leido', 'Solo los administradores pueden marcar notificaciones críticas como no leídas.');
                    }
                }
            }

            // Validación de cambio de tipo: algunos tipos no se pueden cambiar después de creados
            if ($this->has('tipo') && $this->tipo !== $notificacion->tipo) {
                $tiposInmutables = ['pago', 'pedido', 'admin'];
                if (in_array($notificacion->tipo, $tiposInmutables)) {
                    $validator->errors()->add('tipo', 'No se puede cambiar el tipo de esta notificación después de creada.');
                }
            }

            // Validación temporal: notificaciones muy antiguas no se pueden modificar
            if ($notificacion->created_at->isBefore(now()->subDays(30))) {
                $camposRestringidos = ['titulo', 'mensaje', 'tipo', 'user_id'];
                foreach ($camposRestringidos as $campo) {
                    if ($this->has($campo)) {
                        $validator->errors()->add($campo, 'No se pueden modificar notificaciones de más de 30 días de antigüedad.');
                        break;
                    }
                }
            }
        });
    }
} 