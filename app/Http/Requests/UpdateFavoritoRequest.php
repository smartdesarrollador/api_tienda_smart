<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFavoritoRequest extends FormRequest
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
        $favorito = $this->route('favorito');

        return [
            'user_id' => [
                'sometimes',
                'integer',
                'exists:users,id'
            ],
            'producto_id' => [
                'sometimes',
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
            'user_id.exists' => 'El usuario especificado no existe.',
            'producto_id.exists' => 'El producto especificado no existe.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $favorito = $this->route('favorito');

            // Validación personalizada: verificar que el usuario sea cliente si se está cambiando
            if ($this->has('user_id') && $this->user_id !== $favorito->user_id) {
                $user = \App\Models\User::find($this->user_id);
                if ($user && $user->rol !== 'cliente') {
                    $validator->errors()->add('user_id', 'Solo los clientes pueden tener productos favoritos.');
                }
            }

            // Validación personalizada: verificar que el producto esté activo si se está cambiando
            if ($this->has('producto_id') && $this->producto_id !== $favorito->producto_id) {
                $producto = \App\Models\Producto::find($this->producto_id);
                if ($producto && !$producto->activo) {
                    $validator->errors()->add('producto_id', 'No se pueden agregar productos inactivos a favoritos.');
                }
            }

            // Validación de duplicados: si se cambia el producto o usuario, verificar que no exista ya
            if (($this->has('user_id') && $this->user_id !== $favorito->user_id) || 
                ($this->has('producto_id') && $this->producto_id !== $favorito->producto_id)) {
                
                $userId = $this->has('user_id') ? $this->user_id : $favorito->user_id;
                $productoId = $this->has('producto_id') ? $this->producto_id : $favorito->producto_id;
                
                $favoritoExistente = \App\Models\Favorito::where('user_id', $userId)
                    ->where('producto_id', $productoId)
                    ->where('id', '!=', $favorito->id)
                    ->exists();
                
                if ($favoritoExistente) {
                    $validator->errors()->add('producto_id', 'Esta combinación de usuario y producto ya existe en favoritos.');
                }
            }

            // Validación de límites: si se cambia el usuario, verificar límites
            if ($this->has('user_id') && $this->user_id !== $favorito->user_id) {
                $totalFavoritos = \App\Models\Favorito::where('user_id', $this->user_id)
                    ->where('id', '!=', $favorito->id)
                    ->count();
                
                if ($totalFavoritos >= 100) {
                    $validator->errors()->add('user_id', 'El usuario objetivo ha alcanzado el límite máximo de 100 productos favoritos.');
                }
            }

            // Validación temporal: favoritos muy antiguos no se pueden modificar (más de 1 año)
            if ($favorito->created_at->isBefore(now()->subYear())) {
                $validator->errors()->add('general', 'No se pueden modificar favoritos de más de 1 año de antigüedad.');
            }
        });
    }
} 