<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariacionProductoRequest extends FormRequest
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
        $variacionId = $this->route('variacion_producto');
        
        return [
            'producto_id' => [
                'sometimes',
                'integer',
                'exists:productos,id'
            ],
            'sku' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('variaciones_productos', 'sku')->ignore($variacionId)
            ],
            'precio' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:999999.99'
            ],
            'precio_oferta' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
                'lt:precio'
            ],
            'stock' => [
                'sometimes',
                'integer',
                'min:0'
            ],
            'activo' => [
                'sometimes',
                'boolean'
            ],
            'atributos' => [
                'nullable',
                'array'
            ],
            'atributos.*' => [
                'string'
            ],
            'valores_atributos' => [
                'nullable',
                'array'
            ],
            'valores_atributos.*' => [
                'integer',
                'exists:valores_atributos,id'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'producto_id.exists' => 'El producto seleccionado no existe.',
            'sku.unique' => 'El SKU ya existe.',
            'sku.max' => 'El SKU no puede tener más de 100 caracteres.',
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio debe ser mayor a 0.',
            'precio.max' => 'El precio no puede ser mayor a 999,999.99.',
            'precio_oferta.numeric' => 'El precio de oferta debe ser un número.',
            'precio_oferta.min' => 'El precio de oferta debe ser mayor a 0.',
            'precio_oferta.max' => 'El precio de oferta no puede ser mayor a 999,999.99.',
            'precio_oferta.lt' => 'El precio de oferta debe ser menor al precio normal.',
            'stock.integer' => 'El stock debe ser un número entero.',
            'stock.min' => 'El stock no puede ser negativo.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
            'atributos.array' => 'Los atributos deben ser un array.',
            'valores_atributos.array' => 'Los valores de atributos deben ser un array.',
            'valores_atributos.*.exists' => 'Uno de los valores de atributo seleccionados no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'producto_id' => 'producto',
            'sku' => 'SKU',
            'precio' => 'precio',
            'precio_oferta' => 'precio de oferta',
            'stock' => 'stock',
            'activo' => 'estado activo',
            'atributos' => 'atributos',
            'valores_atributos' => 'valores de atributos',
        ];
    }
}
