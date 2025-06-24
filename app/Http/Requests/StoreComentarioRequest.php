<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComentarioRequest extends FormRequest
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
                    return $query->where('rol', 'cliente');
                }),
            ],
            'producto_id' => [
                'required',
                'integer',
                'exists:productos,id',
                Rule::exists('productos', 'id')->where(function ($query) {
                    return $query->where('activo', true);
                }),
            ],
            'comentario' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
            'calificacion' => [
                'nullable',
                'integer',
                'min:1',
                'max:5',
            ],
            'titulo' => [
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
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.exists' => 'El usuario especificado no existe o no es un cliente.',
            'producto_id.required' => 'El producto es obligatorio.',
            'producto_id.exists' => 'El producto especificado no existe o no está activo.',
            'comentario.required' => 'El comentario es obligatorio.',
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

        // Por defecto, los comentarios no están aprobados
        if (!$this->has('aprobado')) {
            $this->merge([
                'aprobado' => false,
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que el usuario no tenga más de 3 comentarios pendientes para el mismo producto
            if ($this->user_id && $this->producto_id) {
                $comentariosPendientes = \App\Models\Comentario::where('user_id', $this->user_id)
                    ->where('producto_id', $this->producto_id)
                    ->where('aprobado', false)
                    ->count();

                if ($comentariosPendientes >= 3) {
                    $validator->errors()->add(
                        'user_id',
                        'Ya tienes 3 comentarios pendientes para este producto. Espera a que sean aprobados.'
                    );
                }
            }

            // Validar que el usuario no tenga un comentario duplicado para el mismo producto en las últimas 24 horas
            if ($this->user_id && $this->producto_id) {
                $comentarioReciente = \App\Models\Comentario::where('user_id', $this->user_id)
                    ->where('producto_id', $this->producto_id)
                    ->where('created_at', '>=', now()->subDay())
                    ->exists();

                if ($comentarioReciente) {
                    $validator->errors()->add(
                        'user_id',
                        'Ya has comentado este producto en las últimas 24 horas.'
                    );
                }
            }

            // Si se proporciona calificación, validar que esté en el rango correcto
            if ($this->calificacion && ($this->calificacion < 1 || $this->calificacion > 5)) {
                $validator->errors()->add(
                    'calificacion',
                    'La calificación debe estar entre 1 y 5 estrellas.'
                );
            }

            // Validar contenido del comentario (no solo espacios en blanco)
            if ($this->comentario && trim($this->comentario) === '') {
                $validator->errors()->add(
                    'comentario',
                    'El comentario no puede estar vacío o contener solo espacios.'
                );
            }

            // Validar que el comentario no sea spam (palabras repetidas)
            if ($this->comentario) {
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
        });
    }
} 