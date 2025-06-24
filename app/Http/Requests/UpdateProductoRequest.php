<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $producto = $this->route('producto');
        
        return [
            'nombre' => [
                'sometimes',
                'string',
                'max:255',
                'min:3',
            ],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('productos', 'slug')->ignore($producto->id),
                'regex:/^[a-z0-9-]+$/',
            ],
            'descripcion' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            'precio' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'precio_oferta' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'lt:precio',
            ],
            'stock' => [
                'sometimes',
                'integer',
                'min:0',
                'max:999999',
            ],
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('productos', 'sku')->ignore($producto->id),
            ],
            'codigo_barras' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('productos', 'codigo_barras')->ignore($producto->id),
            ],
            'imagen_principal' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048', // 2MB
            ],
            'destacado' => [
                'sometimes',
                'boolean',
            ],
            'activo' => [
                'sometimes',
                'boolean',
            ],
            'categoria_id' => [
                'sometimes',
                'integer',
                'exists:categorias,id',
            ],
            'marca' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'modelo' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'garantia' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'meta_title' => [
                'sometimes',
                'nullable',
                'string',
                'max:160',
            ],
            'meta_description' => [
                'sometimes',
                'nullable',
                'string',
                'max:320',
            ],
            'idioma' => [
                'sometimes',
                'nullable',
                'string',
                'max:5',
                Rule::in(['es', 'en', 'fr', 'pt']),
            ],
            'moneda' => [
                'sometimes',
                'nullable',
                'string',
                'max:3',
                Rule::in(['USD', 'EUR', 'COP', 'MXN', 'ARS']),
            ],
            'atributos_extra' => [
                'sometimes',
                'nullable',
                'json',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre no puede exceder los 255 caracteres.',
            'slug.unique' => 'Este slug ya está en uso por otro producto.',
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones.',
            'precio.numeric' => 'El precio debe ser un número válido.',
            'precio.min' => 'El precio no puede ser negativo.',
            'precio_oferta.lt' => 'El precio de oferta debe ser menor al precio regular.',
            'stock.integer' => 'El stock debe ser un número entero.',
            'stock.min' => 'El stock no puede ser negativo.',
            'sku.unique' => 'Este SKU ya está en uso por otro producto.',
            'codigo_barras.unique' => 'Este código de barras ya está en uso.',
            'imagen_principal.image' => 'El archivo debe ser una imagen válida.',
            'imagen_principal.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif o webp.',
            'imagen_principal.max' => 'La imagen no puede exceder los 2MB.',
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
            'meta_title.max' => 'El meta título no puede exceder los 160 caracteres.',
            'meta_description.max' => 'La meta descripción no puede exceder los 320 caracteres.',
            'idioma.in' => 'El idioma debe ser uno de: es, en, fr, pt.',
            'moneda.in' => 'La moneda debe ser una de: USD, EUR, COP, MXN, ARS.',
            'atributos_extra.json' => 'Los atributos extra deben estar en formato JSON válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Actualizar slug si se proporciona un nuevo nombre pero no un slug
        if ($this->has('nombre') && !$this->has('slug')) {
            $this->merge([
                'slug' => $this->generateSlug($this->nombre),
            ]);
        }

        // Normalizar valores booleanos si están presentes
        if ($this->has('destacado')) {
            $this->merge([
                'destacado' => $this->boolean('destacado'),
            ]);
        }

        if ($this->has('activo')) {
            $this->merge([
                'activo' => $this->boolean('activo'),
            ]);
        }

        // Normalizar precios si están presentes
        if ($this->has('precio')) {
            $this->merge([
                'precio' => round((float) $this->precio, 2),
            ]);
        }

        if ($this->has('precio_oferta') && $this->precio_oferta !== null) {
            $this->merge([
                'precio_oferta' => round((float) $this->precio_oferta, 2),
            ]);
        }

        // Generar SKU si se proporciona nuevo nombre pero no SKU
        if ($this->has('nombre') && !$this->has('sku')) {
            $this->merge([
                'sku' => $this->generateSku($this->nombre),
            ]);
        }
    }

    private function generateSlug(string $nombre): string
    {
        $producto = $this->route('producto');
        
        $slug = strtolower(trim($nombre));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Asegurar unicidad excluyendo el producto actual
        $originalSlug = $slug;
        $counter = 1;
        
        while (\App\Models\Producto::where('slug', $slug)
                ->where('id', '!=', $producto->id)
                ->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    private function generateSku(string $nombre): string
    {
        $producto = $this->route('producto');
        
        $sku = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $nombre), 0, 8));
        $sku .= str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Asegurar unicidad excluyendo el producto actual
        while (\App\Models\Producto::where('sku', $sku)
                ->where('id', '!=', $producto->id)
                ->exists()) {
            $sku = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $nombre), 0, 4));
            $sku .= str_pad((string) mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        }
        
        return $sku;
    }
} 