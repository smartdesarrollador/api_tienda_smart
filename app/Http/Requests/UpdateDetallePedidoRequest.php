<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\VariacionProducto;
use App\Models\DetallePedido;

class UpdateDetallePedidoRequest extends FormRequest
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
        $detallePedido = $this->route('detallePedido');
        
        return [
            'producto_id' => [
                'sometimes',
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
                'sometimes',
                'nullable',
                'integer',
                'exists:variaciones_productos,id',
                function ($attribute, $value, $fail) use ($detallePedido) {
                    if ($value) {
                        $productoId = $this->input('producto_id', $detallePedido->producto_id);
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
                'sometimes',
                'integer',
                'min:1',
                'max:999',
                function ($attribute, $value, $fail) use ($detallePedido) {
                    // Verificar que el pedido está en estado editable
                    $pedido = $detallePedido->pedido;
                    if (!in_array($pedido->estado, ['pendiente', 'aprobado'])) {
                        $fail('No se puede modificar la cantidad de items en un pedido en estado: ' . $pedido->estado);
                        return;
                    }
                    
                    $productoId = $this->input('producto_id', $detallePedido->producto_id);
                    $variacionId = $this->input('variacion_id', $detallePedido->variacion_id);
                    
                    // Calcular stock disponible considerando la cantidad actual del detalle
                    if ($variacionId) {
                        $variacion = VariacionProducto::find($variacionId);
                        if ($variacion) {
                            $stockDisponible = $variacion->stock + $detallePedido->cantidad;
                            if ($stockDisponible < $value) {
                                $fail("Stock insuficiente. Disponible: {$stockDisponible}, solicitado: {$value}");
                            }
                        }
                    } else {
                        $producto = Producto::find($productoId);
                        if ($producto) {
                            $stockDisponible = $producto->stock + $detallePedido->cantidad;
                            if ($stockDisponible < $value) {
                                $fail("Stock insuficiente. Disponible: {$stockDisponible}, solicitado: {$value}");
                            }
                        }
                    }
                }
            ],
            'descuento' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99999.99',
                function ($attribute, $value, $fail) use ($detallePedido) {
                    if ($value) {
                        $cantidad = $this->input('cantidad', $detallePedido->cantidad);
                        $productoId = $this->input('producto_id', $detallePedido->producto_id);
                        $variacionId = $this->input('variacion_id', $detallePedido->variacion_id);
                        
                        // Calcular precio unitario
                        if ($variacionId) {
                            $variacion = VariacionProducto::find($variacionId);
                            $precioUnitario = $variacion ? ($variacion->precio_oferta ?? $variacion->precio) : $detallePedido->precio_unitario;
                        } else {
                            $producto = Producto::find($productoId);
                            $precioUnitario = $producto ? ($producto->precio_oferta ?? $producto->precio) : $detallePedido->precio_unitario;
                        }
                        
                        $subtotal = $precioUnitario * $cantidad;
                        
                        if ($value > $subtotal) {
                            $fail('El descuento no puede ser mayor al subtotal del item.');
                        }
                    }
                }
            ],
            'moneda' => [
                'sometimes',
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
            'producto_id.integer' => 'El ID del producto debe ser un número entero.',
            'producto_id.exists' => 'El producto especificado no existe.',
            
            'variacion_id.integer' => 'El ID de la variación debe ser un número entero.',
            'variacion_id.exists' => 'La variación especificada no existe.',
            
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
        $data = [];
        
        // Solo normalizar los campos que están presentes
        if ($this->has('producto_id')) {
            $data['producto_id'] = $this->input('producto_id') ? (int) $this->input('producto_id') : null;
        }
        
        if ($this->has('variacion_id')) {
            $data['variacion_id'] = $this->input('variacion_id') ? (int) $this->input('variacion_id') : null;
        }
        
        if ($this->has('cantidad')) {
            $data['cantidad'] = $this->input('cantidad') ? (int) $this->input('cantidad') : null;
        }
        
        if ($this->has('descuento')) {
            $data['descuento'] = $this->input('descuento') !== null ? (float) $this->input('descuento') : null;
        }
        
        if ($this->has('moneda')) {
            $data['moneda'] = $this->input('moneda');
        }
        
        if (!empty($data)) {
            $this->merge($data);
        }
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        
        // Log de errores de validación para debugging
        \Illuminate\Support\Facades\Log::warning('Errores de validación en UpdateDetallePedidoRequest', [
            'errors' => $errors,
            'input' => $this->all(),
            'detalle_pedido_id' => $this->route('detallePedido')?->id
        ]);

        parent::failedValidation($validator);
    }

    /**
     * Get validated data with additional computed fields.
     */
    public function validatedWithComputed(): array
    {
        $validated = $this->validated();
        $detallePedido = $this->route('detallePedido');
        
        // Si se están actualizando campos que afectan el cálculo, recalcular
        if (isset($validated['producto_id']) || isset($validated['variacion_id']) || isset($validated['cantidad'])) {
            
            // Obtener información del producto/variación (nueva o actual)
            $productoId = $validated['producto_id'] ?? $detallePedido->producto_id;
            $variacionId = $validated['variacion_id'] ?? $detallePedido->variacion_id;
            $cantidad = $validated['cantidad'] ?? $detallePedido->cantidad;
            
            $producto = Producto::find($productoId);
            $variacion = $variacionId ? VariacionProducto::find($variacionId) : null;
            
            // Calcular precio unitario
            $precioUnitario = $variacion 
                ? ($variacion->precio_oferta ?? $variacion->precio)
                : ($producto->precio_oferta ?? $producto->precio);
            
            // Calcular subtotal
            $subtotal = $precioUnitario * $cantidad;
            
            // Calcular impuesto (IGV 18%)
            $descuento = $validated['descuento'] ?? $detallePedido->descuento ?? 0;
            $impuesto = ($subtotal - $descuento) * 0.18;
            
            $validated = array_merge($validated, [
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal,
                'impuesto' => $impuesto,
                'total_linea' => $subtotal - $descuento + $impuesto,
            ]);
        }
        
        return $validated;
    }

    /**
     * Verificar si el detalle puede ser editado
     */
    public function canBeEdited(): bool
    {
        $detallePedido = $this->route('detallePedido');
        $pedido = $detallePedido->pedido;
        
        return in_array($pedido->estado, ['pendiente', 'aprobado']);
    }

    /**
     * Obtener información del stock disponible
     */
    public function getStockInfo(): array
    {
        $detallePedido = $this->route('detallePedido');
        $productoId = $this->input('producto_id', $detallePedido->producto_id);
        $variacionId = $this->input('variacion_id', $detallePedido->variacion_id);
        
        if ($variacionId) {
            $variacion = VariacionProducto::find($variacionId);
            return [
                'tipo' => 'variacion',
                'stock_actual' => $variacion->stock ?? 0,
                'stock_disponible' => ($variacion->stock ?? 0) + $detallePedido->cantidad,
                'cantidad_reservada' => $detallePedido->cantidad,
            ];
        } else {
            $producto = Producto::find($productoId);
            return [
                'tipo' => 'producto',
                'stock_actual' => $producto->stock ?? 0,
                'stock_disponible' => ($producto->stock ?? 0) + $detallePedido->cantidad,
                'cantidad_reservada' => $detallePedido->cantidad,
            ];
        }
    }
} 