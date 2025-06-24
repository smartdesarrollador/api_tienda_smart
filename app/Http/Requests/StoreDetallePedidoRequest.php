<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\VariacionProducto;

class StoreDetallePedidoRequest extends FormRequest
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
            'pedido_id' => [
                'required',
                'integer',
                'exists:pedidos,id',
                function ($attribute, $value, $fail) {
                    $pedido = Pedido::find($value);
                    if ($pedido && !in_array($pedido->estado, ['pendiente', 'aprobado'])) {
                        $fail('No se pueden agregar items a un pedido en estado: ' . $pedido->estado);
                    }
                }
            ],
            'producto_id' => [
                'required',
                'integer',
                'exists:productos,id',
                function ($attribute, $value, $fail) {
                    $producto = Producto::find($value);
                    if ($producto && !$producto->activo) {
                        $fail('El producto seleccionado no está activo.');
                    }
                }
            ],
            'variacion_id' => [
                'nullable',
                'integer',
                'exists:variaciones_productos,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $productoId = $this->input('producto_id');
                        $variacion = VariacionProducto::where('id', $value)
                            ->where('producto_id', $productoId)
                            ->first();
                        
                        if (!$variacion) {
                            $fail('La variación seleccionada no pertenece al producto especificado.');
                        } elseif (!$variacion->activo) {
                            $fail('La variación seleccionada no está activa.');
                        }
                    }
                }
            ],
            'cantidad' => [
                'required',
                'integer',
                'min:1',
                'max:999',
                function ($attribute, $value, $fail) {
                    $productoId = $this->input('producto_id');
                    $variacionId = $this->input('variacion_id');
                    
                    if ($variacionId) {
                        $variacion = VariacionProducto::find($variacionId);
                        if ($variacion && $variacion->stock < $value) {
                            $fail("Stock insuficiente. Disponible: {$variacion->stock}, solicitado: {$value}");
                        }
                    } else {
                        $producto = Producto::find($productoId);
                        if ($producto && $producto->stock < $value) {
                            $fail("Stock insuficiente. Disponible: {$producto->stock}, solicitado: {$value}");
                        }
                    }
                }
            ],
            'descuento' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999.99',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $cantidad = $this->input('cantidad', 1);
                        $productoId = $this->input('producto_id');
                        $variacionId = $this->input('variacion_id');
                        
                        // Calcular precio unitario
                        if ($variacionId) {
                            $variacion = VariacionProducto::find($variacionId);
                            $precioUnitario = $variacion ? ($variacion->precio_oferta ?? $variacion->precio) : 0;
                        } else {
                            $producto = Producto::find($productoId);
                            $precioUnitario = $producto ? ($producto->precio_oferta ?? $producto->precio) : 0;
                        }
                        
                        $subtotal = $precioUnitario * $cantidad;
                        
                        if ($value > $subtotal) {
                            $fail('El descuento no puede ser mayor al subtotal del item.');
                        }
                    }
                }
            ],
            'moneda' => [
                'nullable',
                'string',
                'in:PEN,USD,EUR'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'pedido_id.required' => 'El ID del pedido es obligatorio.',
            'pedido_id.integer' => 'El ID del pedido debe ser un número entero.',
            'pedido_id.exists' => 'El pedido especificado no existe.',
            
            'producto_id.required' => 'El ID del producto es obligatorio.',
            'producto_id.integer' => 'El ID del producto debe ser un número entero.',
            'producto_id.exists' => 'El producto especificado no existe.',
            
            'variacion_id.integer' => 'El ID de la variación debe ser un número entero.',
            'variacion_id.exists' => 'La variación especificada no existe.',
            
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'La cantidad mínima es 1.',
            'cantidad.max' => 'La cantidad máxima es 999.',
            
            'descuento.numeric' => 'El descuento debe ser un número.',
            'descuento.min' => 'El descuento no puede ser negativo.',
            'descuento.max' => 'El descuento máximo es 99,999.99.',
            
            'moneda.string' => 'La moneda debe ser una cadena de texto.',
            'moneda.in' => 'La moneda debe ser PEN, USD o EUR.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'pedido_id' => 'pedido',
            'producto_id' => 'producto',
            'variacion_id' => 'variación',
            'cantidad' => 'cantidad',
            'descuento' => 'descuento',
            'moneda' => 'moneda',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalizar datos de entrada
        $this->merge([
            'pedido_id' => $this->input('pedido_id') ? (int) $this->input('pedido_id') : null,
            'producto_id' => $this->input('producto_id') ? (int) $this->input('producto_id') : null,
            'variacion_id' => $this->input('variacion_id') ? (int) $this->input('variacion_id') : null,
            'cantidad' => $this->input('cantidad') ? (int) $this->input('cantidad') : null,
            'descuento' => $this->input('descuento') ? (float) $this->input('descuento') : 0,
            'moneda' => $this->input('moneda', 'PEN'),
        ]);
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        
        // Log de errores de validación para debugging
        \Illuminate\Support\Facades\Log::warning('Errores de validación en StoreDetallePedidoRequest', [
            'errors' => $errors,
            'input' => $this->all()
        ]);

        parent::failedValidation($validator);
    }

    /**
     * Get validated data with additional computed fields.
     */
    public function validatedWithComputed(): array
    {
        $validated = $this->validated();
        
        // Obtener información del producto/variación
        $producto = Producto::find($validated['producto_id']);
        $variacion = null;
        
        if (isset($validated['variacion_id'])) {
            $variacion = VariacionProducto::find($validated['variacion_id']);
        }
        
        // Calcular precio unitario
        $precioUnitario = $variacion 
            ? ($variacion->precio_oferta ?? $variacion->precio)
            : ($producto->precio_oferta ?? $producto->precio);
        
        // Calcular subtotal
        $subtotal = $precioUnitario * $validated['cantidad'];
        
        // Calcular impuesto (IGV 18%)
        $descuento = $validated['descuento'] ?? 0;
        $impuesto = ($subtotal - $descuento) * 0.18;
        
        return array_merge($validated, [
            'precio_unitario' => $precioUnitario,
            'subtotal' => $subtotal,
            'impuesto' => $impuesto,
            'total_linea' => $subtotal - $descuento + $impuesto,
        ]);
    }
} 