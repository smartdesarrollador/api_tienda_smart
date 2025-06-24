<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComentarioRequest extends FormRequest
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
        $comentario = $this->route('comentario');
        
        return [
            'user_id' => [
                'sometimes',
                'integer',
                'exists:users,id',
                Rule::exists('users', 'id')->where(function ($query) {
                    return $query->where('rol', 'cliente');
                }),
            ],
            'producto_id' => [
                'sometimes',
                'integer',
                'exists:productos,id',
                Rule::exists('productos', 'id')->where(function ($query) {
                    return $query->where('activo', true);
                }),
            ],
            'comentario' => [
                'sometimes',
                'string',
                'min:10',
                'max:1000',
            ],
            'calificacion' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:5',
            ],
            'titulo' => [
                'sometimes',
                'nullable',
                'string',
                'min:5',
                'max:100',
            ],
            'aprobado' => [
                'sometimes',
                'boolean',
            ],
            'respuesta_admin' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'El usuario especificado no existe o no es un cliente.',
            'producto_id.exists' => 'El producto especificado no existe o no está activo.',
            'comentario.min' => 'El comentario debe tener al menos 10 caracteres.',
            'comentario.max' => 'El comentario no puede exceder 1000 caracteres.',
            'calificacion.integer' => 'La calificación debe ser un número entero.',
            'calificacion.min' => 'La calificación mínima es 1 estrella.',
            'calificacion.max' => 'La calificación máxima es 5 estrellas.',
            'titulo.min' => 'El título debe tener al menos 5 caracteres.',
            'titulo.max' => 'El título no puede exceder 100 caracteres.',
            'respuesta_admin.max' => 'La respuesta del administrador no puede exceder 500 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'usuario',
            'producto_id' => 'producto',
            'comentario' => 'comentario',
            'calificacion' => 'calificación',
            'titulo' => 'título',
            'aprobado' => 'estado de aprobación',
            'respuesta_admin' => 'respuesta del administrador',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalizar el comentario
        if ($this->has('comentario')) {
            $this->merge([
                'comentario' => trim($this->comentario),
            ]);
        }

        // Normalizar el título
        if ($this->has('titulo')) {
            $this->merge([
                'titulo' => trim($this->titulo),
            ]);
        }

        // Normalizar la respuesta del admin
        if ($this->has('respuesta_admin')) {
            $this->merge([
                'respuesta_admin' => trim($this->respuesta_admin),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $comentario = $this->route('comentario');
            
            if (!$comentario) {
                return;
            }

            // Validar que no se pueda cambiar el usuario o producto una vez creado el comentario
            if ($this->has('user_id') && $this->user_id != $comentario->user_id) {
                $validator->errors()->add(
                    'user_id',
                    'No se puede cambiar el usuario de un comentario existente.'
                );
            }

            if ($this->has('producto_id') && $this->producto_id != $comentario->producto_id) {
                $validator->errors()->add(
                    'producto_id',
                    'No se puede cambiar el producto de un comentario existente.'
                );
            }

            // Validar que el comentario no esté aprobado si se intenta modificar el contenido
            if ($comentario->aprobado && ($this->has('comentario') || $this->has('titulo') || $this->has('calificacion'))) {
                // Solo permitir si se está desaprobando
                if (!$this->has('aprobado') || $this->aprobado !== false) {
                    $validator->errors()->add(
                        'comentario',
                        'No se puede modificar el contenido de un comentario ya aprobado. Primero debe ser desaprobado.'
                    );
                }
            }

            // Validar contenido del comentario (no solo espacios en blanco)
            if ($this->has('comentario') && trim($this->comentario) === '') {
                $validator->errors()->add(
                    'comentario',
                    'El comentario no puede estar vacío o contener solo espacios.'
                );
            }

            // Validar que el comentario no sea spam (palabras repetidas)
            if ($this->has('comentario')) {
                $palabras = explode(' ', strtolower(trim($this->comentario)));
                $palabrasUnicas = array_unique($palabras);
                
                // Si más del 70% son palabras repetidas, es probable spam
                if (count($palabras) > 5 && (count($palabrasUnicas) / count($palabras)) < 0.3) {
                    $validator->errors()->add(
                        'comentario',
                        'El comentario parece contener contenido repetitivo. Por favor, escribe un comentario más variado.'
                    );
                }
            }

            // Validar que solo administradores puedan agregar respuesta_admin
            if ($this->has('respuesta_admin') && $this->respuesta_admin) {
                $user = auth()->user();
                if (!$user || $user->rol !== 'administrador') {
                    $validator->errors()->add(
                        'respuesta_admin',
                        'Solo los administradores pueden agregar respuestas a los comentarios.'
                    );
                }
            }

            // Validar que solo administradores puedan cambiar el estado de aprobación
            if ($this->has('aprobado')) {
                $user = auth()->user();
                if (!$user || $user->rol !== 'administrador') {
                    $validator->errors()->add(
                        'aprobado',
                        'Solo los administradores pueden cambiar el estado de aprobación.'
                    );
                }
            }

            // Validar que si se está aprobando, el comentario tenga contenido válido
            if ($this->has('aprobado') && $this->aprobado === true) {
                $comentarioTexto = $this->has('comentario') ? $this->comentario : $comentario->comentario;
                
                if (!$comentarioTexto || strlen(trim($comentarioTexto)) < 10) {
                    $validator->errors()->add(
                        'aprobado',
                        'No se puede aprobar un comentario sin contenido válido (mínimo 10 caracteres).'
                    );
                }
            }

            // Validar coherencia entre calificación y comentario
            if ($this->has('calificacion') && $this->has('comentario')) {
                $calificacion = $this->calificacion;
                $comentarioTexto = strtolower($this->comentario);
                
                // Palabras que indican comentarios negativos
                $palabrasNegativas = ['malo', 'pésimo', 'horrible', 'terrible', 'defectuoso', 'no funciona', 'no recomiendo'];
                $palabrasPositivas = ['excelente', 'bueno', 'genial', 'perfecto', 'recomiendo', 'me gusta', 'fantástico'];
                
                $tieneNegativas = false;
                $tienePositivas = false;
                
                foreach ($palabrasNegativas as $palabra) {
                    if (strpos($comentarioTexto, $palabra) !== false) {
                        $tieneNegativas = true;
                        break;
                    }
                }
                
                foreach ($palabrasPositivas as $palabra) {
                    if (strpos($comentarioTexto, $palabra) !== false) {
                        $tienePositivas = true;
                        break;
                    }
                }
                
                // Si tiene palabras muy negativas pero calificación alta
                if ($tieneNegativas && $calificacion >= 4) {
                    $validator->errors()->add(
                        'calificacion',
                        'La calificación parece inconsistente con el contenido del comentario.'
                    );
                }
                
                // Si tiene palabras muy positivas pero calificación baja
                if ($tienePositivas && $calificacion <= 2) {
                    $validator->errors()->add(
                        'calificacion',
                        'La calificación parece inconsistente con el contenido del comentario.'
                    );
                }
            }
        });
    }
} 