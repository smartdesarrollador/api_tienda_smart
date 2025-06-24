<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFavoritoRequest extends FormRequest
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
            'producto_id' => [
                'required',
                'integer',
                'exists:productos,id'
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
            'producto_id.required' => 'El producto es obligatorio.',
            'producto_id.exists' => 'El producto especificado no existe.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validación personalizada: verificar que el usuario sea cliente
            if ($this->has('user_id')) {
                $user = \App\Models\User::find($this->user_id);
                if ($user && $user->rol !== 'cliente') {
                    $validator->errors()->add('user_id', 'Solo los clientes pueden agregar productos a favoritos.');
                }
            }

            // Validación personalizada: verificar que el producto esté activo
            if ($this->has('producto_id')) {
                $producto = \App\Models\Producto::find($this->producto_id);
                if ($producto && !$producto->activo) {
                    $validator->errors()->add('producto_id', 'No se pueden agregar productos inactivos a favoritos.');
                }
            }

            // Validación de duplicados: no permitir favoritos duplicados
            if ($this->has('user_id') && $this->has('producto_id')) {
                $favoritoExistente = \App\Models\Favorito::where('user_id', $this->user_id)
                    ->where('producto_id', $this->producto_id)
                    ->exists();
                
                if ($favoritoExistente) {
                    $validator->errors()->add('producto_id', 'Este producto ya está en tus favoritos.');
                }
            }

            // Validación de límites: máximo 100 favoritos por usuario
            if ($this->has('user_id')) {
                $totalFavoritos = \App\Models\Favorito::where('user_id', $this->user_id)->count();
                
                if ($totalFavoritos >= 100) {
                    $validator->errors()->add('user_id', 'Has alcanzado el límite máximo de 100 productos favoritos.');
                }
            }
        });
    }
} 