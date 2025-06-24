<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                'min:3',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'unique:productos,slug',
                'regex:/^[a-z0-9-]+$/',
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'precio' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'precio_oferta' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'lt:precio',
            ],
            'stock' => [
                'required',
                'integer',
                'min:0',
                'max:999999',
            ],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                'unique:productos,sku',
            ],
            'codigo_barras' => [
                'nullable',
                'string',
                'max:50',
                'unique:productos,codigo_barras',
            ],
            'imagen_principal' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048', // 2MB
            ],
            'destacado' => [
                'nullable',
                'boolean',
            ],
            'activo' => [
                'nullable',
                'boolean',
            ],
            'categoria_id' => [
                'required',
                'integer',
                'exists:categorias,id',
            ],
            'marca' => [
                'nullable',
                'string',
                'max:100',
            ],
            'modelo' => [
                'nullable',
                'string',
                'max:100',
            ],
            'garantia' => [
                'nullable',
                'string',
                'max:100',
            ],
            'meta_title' => [
                'nullable',
                'string',
                'max:160',
            ],
            'meta_description' => [
                'nullable',
                'string',
                'max:320',
            ],
            'idioma' => [
                'nullable',
                'string',
                'max:5',
                Rule::in(['es', 'en', 'fr', 'pt']),
            ],
            'moneda' => [
                'nullable',
                'string',
                'max:3',
                Rule::in(['USD', 'EUR', 'COP', 'MXN', 'ARS']),
            ],
            'atributos_extra' => [
                'nullable',
                'json',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre no puede exceder los 255 caracteres.',
            'slug.unique' => 'Este slug ya está en uso por otro producto.',
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser un número válido.',
            'precio.min' => 'El precio no puede ser negativo.',
            'precio_oferta.lt' => 'El precio de oferta debe ser menor al precio regular.',
            'stock.required' => 'La cantidad en stock es obligatoria.',
            'stock.integer' => 'El stock debe ser un número entero.',
            'stock.min' => 'El stock no puede ser negativo.',
            'sku.unique' => 'Este SKU ya está en uso por otro producto.',
            'codigo_barras.unique' => 'Este código de barras ya está en uso.',
            'imagen_principal.image' => 'El archivo debe ser una imagen válida.',
            'imagen_principal.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif o webp.',
            'imagen_principal.max' => 'La imagen no puede exceder los 2MB.',
            'categoria_id.required' => 'La categoría es obligatoria.',
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
        // Generar slug automáticamente si no se proporciona
        if (!$this->slug && $this->nombre) {
            $this->merge([
                'slug' => $this->generateSlug($this->nombre),
            ]);
        }

        // Normalizar valores booleanos
        $this->merge([
            'destacado' => $this->boolean('destacado'),
            'activo' => $this->filled('activo') ? $this->boolean('activo') : true, // Activo por defecto
        ]);

        // Normalizar precios
        if ($this->precio) {
            $this->merge([
                'precio' => round((float) $this->precio, 2),
            ]);
        }

        if ($this->precio_oferta) {
            $this->merge([
                'precio_oferta' => round((float) $this->precio_oferta, 2),
            ]);
        }

        // Generar SKU automático si no se proporciona
        if (!$this->sku && $this->nombre) {
            $this->merge([
                'sku' => $this->generateSku($this->nombre),
            ]);
        }

        // Valores por defecto
        $this->merge([
            'idioma' => $this->idioma ?? 'es',
            'moneda' => $this->moneda ?? 'USD',
        ]);
    }

    private function generateSlug(string $nombre): string
    {
        $slug = strtolower(trim($nombre));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Asegurar unicidad
        $originalSlug = $slug;
        $counter = 1;
        
        while (\App\Models\Producto::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    private function generateSku(string $nombre): string
    {
        $sku = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $nombre), 0, 8));
        $sku .= str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Asegurar unicidad
        while (\App\Models\Producto::where('sku', $sku)->exists()) {
            $sku = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $nombre), 0, 4));
            $sku .= str_pad((string) mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        }
        
        return $sku;
    }
} 