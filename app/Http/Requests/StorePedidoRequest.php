<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePedidoRequest extends FormRequest
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
            // Usuario
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('activo', true);
                })
            ],

            // Método de pago
            'metodo_pago_id' => [
                'nullable',
                'integer',
                'exists:metodos_pago,id',
                Rule::exists('metodos_pago', 'id')->where(function ($query) {
                    $query->where('activo', true);
                })
            ],

            // Tipo de pago y configuración
            'tipo_pago' => [
                'required',
                'string',
                'in:contado,credito,transferencia,tarjeta,yape,plin,paypal'
            ],
            'cuotas' => [
                'nullable',
                'integer',
                'min:1',
                'max:24',
                'required_if:tipo_pago,credito'
            ],
            'moneda' => [
                'nullable',
                'string',
                'in:PEN,USD,EUR',
                'max:5'
            ],
            'canal_venta' => [
                'nullable',
                'string',
                'in:web,app,tienda_fisica,telefono,whatsapp',
                'max:50'
            ],

            // Información adicional
            'observaciones' => [
                'nullable',
                'string',
                'max:1000'
            ],

            // Cupón opcional
            'cupon_codigo' => [
                'nullable',
                'string',
                'max:50',
                'exists:cupones,codigo'
            ],

            // Datos del cliente y envío
            'datos_cliente' => [
                'nullable',
                'array'
            ],
            'datos_cliente.nombre' => [
                'required_with:datos_cliente',
                'string',
                'max:255'
            ],
            'datos_cliente.email' => [
                'required_with:datos_cliente',
                'email',
                'max:255'
            ],
            'datos_cliente.telefono' => [
                'nullable',
                'string',
                'max:20'
            ],

            'datos_envio' => [
                'nullable',
                'array'
            ],
            'datos_envio.direccion' => [
                'required_with:datos_envio',
                'string',
                'max:500'
            ],
            'datos_envio.ciudad' => [
                'required_with:datos_envio',
                'string',
                'max:100'
            ],
            'datos_envio.codigo_postal' => [
                'nullable',
                'string',
                'max:10'
            ],

            'metodo_envio' => [
                'nullable',
                'array'
            ],
            'metodo_envio.tipo' => [
                'required_with:metodo_envio',
                'string',
                'in:domicilio,recojo_tienda,courier'
            ],
            'metodo_envio.costo' => [
                'required_with:metodo_envio',
                'numeric',
                'min:0'
            ],

            // Costos adicionales
            'costo_envio' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999.99'
            ],

            // Items del pedido (array requerido)
            'items' => [
                'required',
                'array',
                'min:1',
                'max:50' // Máximo 50 items por pedido
            ],
            'items.*.producto_id' => [
                'required',
                'integer',
                'exists:productos,id'
            ],
            'items.*.variacion_id' => [
                'nullable',
                'integer',
                'exists:variaciones_productos,id'
            ],
            'items.*.cantidad' => [
                'required',
                'integer',
                'min:1',
                'max:999'
            ],
            'items.*.descuento' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999.99'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Usuario
            'user_id.required' => 'El usuario es requerido.',
            'user_id.exists' => 'El usuario seleccionado no existe o está inactivo.',

            // Método de pago
            'metodo_pago_id.exists' => 'El método de pago seleccionado no existe o está inactivo.',

            // Tipo de pago
            'tipo_pago.required' => 'El tipo de pago es requerido.',
            'tipo_pago.in' => 'El tipo de pago debe ser: contado, crédito, transferencia, tarjeta, yape, plin o paypal.',
            'cuotas.required_if' => 'Las cuotas son requeridas para pagos a crédito.',
            'cuotas.min' => 'El número de cuotas debe ser al menos 1.',
            'cuotas.max' => 'El número de cuotas no puede ser mayor a 24.',

            // Moneda y canal
            'moneda.in' => 'La moneda debe ser PEN, USD o EUR.',
            'canal_venta.in' => 'El canal de venta debe ser: web, app, tienda_fisica, telefono o whatsapp.',

            // Información adicional
            'observaciones.max' => 'Las observaciones no pueden exceder 1000 caracteres.',
            'cupon_codigo.exists' => 'El código de cupón no existe.',

            // Datos del cliente
            'datos_cliente.nombre.required_with' => 'El nombre del cliente es requerido.',
            'datos_cliente.email.required_with' => 'El email del cliente es requerido.',
            'datos_cliente.email.email' => 'El email del cliente debe ser válido.',
            'datos_cliente.telefono.max' => 'El teléfono no puede exceder 20 caracteres.',

            // Datos de envío
            'datos_envio.direccion.required_with' => 'La dirección de envío es requerida.',
            'datos_envio.ciudad.required_with' => 'La ciudad de envío es requerida.',
            'datos_envio.codigo_postal.max' => 'El código postal no puede exceder 10 caracteres.',

            // Método de envío
            'metodo_envio.tipo.required_with' => 'El tipo de envío es requerido.',
            'metodo_envio.tipo.in' => 'El tipo de envío debe ser: domicilio, recojo_tienda o courier.',
            'metodo_envio.costo.required_with' => 'El costo de envío es requerido.',
            'metodo_envio.costo.min' => 'El costo de envío no puede ser negativo.',

            // Costos adicionales
            'costo_envio.min' => 'El costo de envío no puede ser negativo.',
            'costo_envio.max' => 'El costo de envío no puede exceder 9,999.99.',

            // Items del pedido
            'items.required' => 'Debe incluir al menos un item en el pedido.',
            'items.min' => 'Debe incluir al menos un item en el pedido.',
            'items.max' => 'No se pueden incluir más de 50 items por pedido.',
            'items.*.producto_id.required' => 'El ID del producto es requerido para cada item.',
            'items.*.producto_id.exists' => 'Uno o más productos seleccionados no existen.',
            'items.*.variacion_id.exists' => 'Una o más variaciones seleccionadas no existen.',
            'items.*.cantidad.required' => 'La cantidad es requerida para cada item.',
            'items.*.cantidad.min' => 'La cantidad mínima por item es 1.',
            'items.*.cantidad.max' => 'La cantidad máxima por item es 999.',
            'items.*.descuento.min' => 'El descuento no puede ser negativo.',
            'items.*.descuento.max' => 'El descuento por item no puede exceder 99,999.99.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'usuario',
            'tipo_pago' => 'tipo de pago',
            'cuotas' => 'número de cuotas',
            'moneda' => 'moneda',
            'canal_venta' => 'canal de venta',
            'observaciones' => 'observaciones',
            'cupon_codigo' => 'código de cupón',
            'items' => 'items del pedido',
            'items.*.producto_id' => 'producto',
            'items.*.variacion_id' => 'variación',
            'items.*.cantidad' => 'cantidad',
            'items.*.descuento' => 'descuento',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que si se especifica variacion_id, pertenezca al producto
            if ($this->has('items')) {
                foreach ($this->items as $index => $item) {
                    if (isset($item['variacion_id']) && isset($item['producto_id'])) {
                        $variacion = \App\Models\VariacionProducto::find($item['variacion_id']);
                        if ($variacion && $variacion->producto_id != $item['producto_id']) {
                            $validator->errors()->add(
                                "items.{$index}.variacion_id",
                                'La variación no pertenece al producto seleccionado.'
                            );
                        }
                    }
                }
            }

            // Validar que el usuario tenga límite de crédito suficiente si es pago a crédito
            if ($this->tipo_pago === 'credito' && $this->user_id) {
                $usuario = \App\Models\User::find($this->user_id);
                if ($usuario && $usuario->limite_credito <= 0) {
                    $validator->errors()->add('tipo_pago', 'El usuario no tiene límite de crédito disponible.');
                }
            }

            // Validar que el cupón esté vigente y disponible
            if ($this->cupon_codigo) {
                $cupon = \App\Models\Cupon::where('codigo', $this->cupon_codigo)
                    ->activos()
                    ->vigentes()
                    ->disponibles()
                    ->first();
                
                if (!$cupon) {
                    $validator->errors()->add('cupon_codigo', 'El cupón no está vigente o ya fue utilizado.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalizar datos
        $this->merge([
            'tipo_pago' => strtolower($this->tipo_pago ?? ''),
            'moneda' => strtoupper($this->moneda ?? 'PEN'),
            'canal_venta' => strtolower($this->canal_venta ?? 'web'),
            'cupon_codigo' => strtoupper($this->cupon_codigo ?? ''),
        ]);

        // Limpiar y normalizar items
        if ($this->has('items') && is_array($this->items)) {
            $itemsLimpios = [];
            foreach ($this->items as $item) {
                if (isset($item['producto_id']) && isset($item['cantidad'])) {
                    $itemsLimpios[] = [
                        'producto_id' => (int) $item['producto_id'],
                        'variacion_id' => isset($item['variacion_id']) ? (int) $item['variacion_id'] : null,
                        'cantidad' => (int) $item['cantidad'],
                        'descuento' => isset($item['descuento']) ? (float) $item['descuento'] : 0,
                    ];
                }
            }
            $this->merge(['items' => $itemsLimpios]);
        }
    }
} 