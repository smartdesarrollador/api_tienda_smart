<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMetodoPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ajustar según políticas de autorización
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                'unique:metodos_pago,nombre'
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'unique:metodos_pago,slug'
            ],
            'tipo' => [
                'required',
                'string',
                Rule::in([
                    MetodoPago::TIPO_TARJETA_CREDITO,
                    MetodoPago::TIPO_TARJETA_DEBITO,
                    MetodoPago::TIPO_BILLETERA_DIGITAL,
                    MetodoPago::TIPO_TRANSFERENCIA,
                    MetodoPago::TIPO_EFECTIVO,
                    MetodoPago::TIPO_CRIPTOMONEDA,
                ])
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'logo' => [
                'nullable',
                'string',
                'max:500'
            ],
            'activo' => [
                'boolean'
            ],
            'requiere_verificacion' => [
                'boolean'
            ],
            'comision_porcentaje' => [
                'numeric',
                'min:0',
                'max:100',
                'decimal:0,3'
            ],
            'comision_fija' => [
                'numeric',
                'min:0',
                'decimal:0,2'
            ],
            'monto_minimo' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,2'
            ],
            'monto_maximo' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,2',
                'gt:monto_minimo'
            ],
            'orden' => [
                'integer',
                'min:0'
            ],
            'configuracion' => [
                'nullable',
                'array'
            ],
            'paises_disponibles' => [
                'nullable',
                'array'
            ],
            'paises_disponibles.*' => [
                'string',
                'size:2' // Códigos de país ISO 3166-1 alpha-2
            ],
            'proveedor' => [
                'nullable',
                'string',
                'max:100'
            ],
            'moneda_soportada' => [
                'string',
                'size:3', // Códigos de moneda ISO 4217
                'default:PEN'
            ],
            'permite_cuotas' => [
                'boolean'
            ],
            'cuotas_maximas' => [
                'nullable',
                'integer',
                'min:2',
                'max:60',
                'required_if:permite_cuotas,true'
            ],
            'instrucciones' => [
                'nullable',
                'string',
                'max:2000'
            ],
            'icono_clase' => [
                'nullable',
                'string',
                'max:100'
            ],
            'color_primario' => [
                'nullable',
                'string',
                'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/' // Hexadecimal color
            ],
            'tiempo_procesamiento' => [
                'nullable',
                'integer',
                'min:0',
                'max:10080' // Máximo 1 semana en minutos
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del método de pago es obligatorio.',
            'nombre.unique' => 'Ya existe un método de pago con este nombre.',
            'tipo.required' => 'El tipo de método de pago es obligatorio.',
            'tipo.in' => 'El tipo seleccionado no es válido.',
            'comision_porcentaje.max' => 'La comisión por porcentaje no puede ser mayor al 100%.',
            'comision_porcentaje.decimal' => 'La comisión por porcentaje debe tener máximo 3 decimales.',
            'comision_fija.decimal' => 'La comisión fija debe tener máximo 2 decimales.',
            'monto_maximo.gt' => 'El monto máximo debe ser mayor al monto mínimo.',
            'paises_disponibles.*.size' => 'Los códigos de país deben tener exactamente 2 caracteres.',
            'moneda_soportada.size' => 'El código de moneda debe tener exactamente 3 caracteres.',
            'cuotas_maximas.required_if' => 'Las cuotas máximas son obligatorias cuando se permite pagos en cuotas.',
            'cuotas_maximas.max' => 'El número máximo de cuotas no puede exceder 60.',
            'color_primario.regex' => 'El color debe estar en formato hexadecimal (#FFFFFF).',
            'tiempo_procesamiento.max' => 'El tiempo de procesamiento no puede exceder 1 semana (10080 minutos).',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convertir valores a tipos apropiados
        if ($this->has('activo')) {
            $this->merge(['activo' => filter_var($this->activo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]);
        }

        if ($this->has('requiere_verificacion')) {
            $this->merge(['requiere_verificacion' => filter_var($this->requiere_verificacion, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]);
        }

        if ($this->has('permite_cuotas')) {
            $this->merge(['permite_cuotas' => filter_var($this->permite_cuotas, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]);
        }
    }
} 