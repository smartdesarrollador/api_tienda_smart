<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\DatosFacturacion;

class UpdateDatosFacturacionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Autorización manejada por middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $datosFacturacionId = $this->route('datos_facturacion') ?? $this->route('datosFacturacion');

        return [
            // Cliente relacionado (generalmente no se cambia)
            'cliente_id' => ['sometimes', 'integer', 'exists:clientes,id'],
            
            // Información del documento
            'tipo_documento' => ['sometimes', Rule::in(DatosFacturacion::TIPOS_DOCUMENTO)],
            'numero_documento' => [
                'sometimes',
                'string',
                'max:20',
                'regex:/^[0-9A-Za-z]+$/',
                Rule::unique('datos_facturacion', 'numero_documento')
                    ->ignore($datosFacturacionId)
                    ->where('cliente_id', $this->input('cliente_id'))
                    ->where('tipo_documento', $this->input('tipo_documento'))
            ],
            
            // Nombres y razón social
            'nombre_facturacion' => ['sometimes', 'string', 'max:255'],
            'razon_social' => [
                Rule::requiredIf($this->input('tipo_documento') === DatosFacturacion::TIPO_RUC),
                'sometimes',
                'nullable',
                'string',
                'max:255'
            ],
            
            // Dirección fiscal
            'direccion_fiscal' => ['sometimes', 'string', 'max:255'],
            'distrito_fiscal' => ['sometimes', 'string', 'max:100'],
            'provincia_fiscal' => ['sometimes', 'string', 'max:100'],
            'departamento_fiscal' => ['sometimes', 'string', 'max:100'],
            'codigo_postal_fiscal' => ['sometimes', 'nullable', 'string', 'max:10'],
            
            // Contacto
            'telefono_fiscal' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[\+0-9\s\-\(\)]+$/'],
            'email_facturacion' => ['sometimes', 'nullable', 'email', 'max:255'],
            
            // Estados
            'predeterminado' => ['sometimes', 'nullable', 'boolean'],
            'activo' => ['sometimes', 'nullable', 'boolean'],
            
            // Datos empresariales (cuando aplique)
            'contacto_empresa' => [
                Rule::requiredIf($this->input('tipo_documento') === DatosFacturacion::TIPO_RUC),
                'sometimes',
                'nullable',
                'string',
                'max:255'
            ],
            'giro_negocio' => [
                Rule::requiredIf($this->input('tipo_documento') === DatosFacturacion::TIPO_RUC),
                'sometimes',
                'nullable',
                'string',
                'max:255'
            ],
            
            // Datos adicionales (JSON)
            'datos_adicionales' => ['sometimes', 'nullable', 'array'],
            'datos_adicionales.referencia_fiscal' => ['sometimes', 'nullable', 'string', 'max:100'],
            'datos_adicionales.codigo_ubigeo' => ['sometimes', 'nullable', 'string', 'max:10'],
            'datos_adicionales.agente_retencion' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cliente_id.exists' => 'El cliente especificado no existe.',
            
            'tipo_documento.in' => 'El tipo de documento debe ser: dni, ruc, pasaporte o carnet_extranjeria.',
            
            'numero_documento.regex' => 'El número de documento debe contener solo números y letras.',
            'numero_documento.unique' => 'Este número de documento ya está registrado para este cliente y tipo.',
            'numero_documento.max' => 'El número de documento no puede tener más de 20 caracteres.',
            
            'nombre_facturacion.max' => 'El nombre de facturación no puede tener más de 255 caracteres.',
            
            'razon_social.required_if' => 'La razón social es obligatoria para documentos RUC.',
            'razon_social.max' => 'La razón social no puede tener más de 255 caracteres.',
            
            'direccion_fiscal.max' => 'La dirección fiscal no puede tener más de 255 caracteres.',
            'distrito_fiscal.max' => 'El distrito fiscal no puede tener más de 100 caracteres.',
            'provincia_fiscal.max' => 'La provincia fiscal no puede tener más de 100 caracteres.',
            'departamento_fiscal.max' => 'El departamento fiscal no puede tener más de 100 caracteres.',
            
            'telefono_fiscal.regex' => 'El teléfono fiscal tiene un formato inválido.',
            'email_facturacion.email' => 'El email de facturación debe tener un formato válido.',
            
            'contacto_empresa.required_if' => 'El contacto de empresa es obligatorio para documentos RUC.',
            'giro_negocio.required_if' => 'El giro de negocio es obligatorio para documentos RUC.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cliente_id' => 'cliente',
            'tipo_documento' => 'tipo de documento',
            'numero_documento' => 'número de documento',
            'nombre_facturacion' => 'nombre de facturación',
            'razon_social' => 'razón social',
            'direccion_fiscal' => 'dirección fiscal',
            'distrito_fiscal' => 'distrito fiscal',
            'provincia_fiscal' => 'provincia fiscal',
            'departamento_fiscal' => 'departamento fiscal',
            'codigo_postal_fiscal' => 'código postal fiscal',
            'telefono_fiscal' => 'teléfono fiscal',
            'email_facturacion' => 'email de facturación',
            'predeterminado' => 'predeterminado',
            'activo' => 'activo',
            'contacto_empresa' => 'contacto de empresa',
            'giro_negocio' => 'giro de negocio',
            'datos_adicionales' => 'datos adicionales',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Limpiar y formatear número de documento
        if ($this->has('numero_documento') && $this->numero_documento) {
            $this->merge([
                'numero_documento' => preg_replace('/[^0-9A-Za-z]/', '', $this->numero_documento)
            ]);
        }

        // Limpiar teléfono fiscal
        if ($this->has('telefono_fiscal') && $this->telefono_fiscal) {
            $this->merge([
                'telefono_fiscal' => preg_replace('/[^0-9+]/', '', $this->telefono_fiscal)
            ]);
        }

        // Validar email de facturación
        if ($this->has('email_facturacion') && $this->email_facturacion) {
            $this->merge([
                'email_facturacion' => strtolower(trim($this->email_facturacion))
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validaciones adicionales según tipo de documento
            $this->validateDocumentByType($validator);
            
            // Validar que solo puede haber un predeterminado por cliente
            $this->validateSingleDefault($validator);
        });
    }

    /**
     * Validar documento según su tipo
     */
    private function validateDocumentByType($validator): void
    {
        $tipoDocumento = $this->input('tipo_documento');
        $numeroDocumento = $this->input('numero_documento');

        if (!$tipoDocumento || !$numeroDocumento) {
            return;
        }

        switch ($tipoDocumento) {
            case DatosFacturacion::TIPO_DNI:
                if (strlen($numeroDocumento) !== 8 || !is_numeric($numeroDocumento)) {
                    $validator->errors()->add('numero_documento', 'El DNI debe tener exactamente 8 números.');
                }
                break;

            case DatosFacturacion::TIPO_RUC:
                if (strlen($numeroDocumento) !== 11 || !is_numeric($numeroDocumento)) {
                    $validator->errors()->add('numero_documento', 'El RUC debe tener exactamente 11 números.');
                } else {
                    // Validar dígito verificador del RUC
                    if (!$this->validateRucCheckDigit($numeroDocumento)) {
                        $validator->errors()->add('numero_documento', 'El RUC ingresado no es válido.');
                    }
                }
                break;

            case DatosFacturacion::TIPO_CARNET_EXTRANJERIA:
                if (strlen($numeroDocumento) !== 9 || !is_numeric($numeroDocumento)) {
                    $validator->errors()->add('numero_documento', 'El carnet de extranjería debe tener exactamente 9 números.');
                }
                break;

            case DatosFacturacion::TIPO_PASAPORTE:
                if (strlen($numeroDocumento) < 6 || strlen($numeroDocumento) > 12) {
                    $validator->errors()->add('numero_documento', 'El pasaporte debe tener entre 6 y 12 caracteres.');
                }
                break;
        }
    }

    /**
     * Validar que solo puede haber un predeterminado por cliente
     */
    private function validateSingleDefault($validator): void
    {
        if ($this->input('predeterminado') === true) {
            $datosFacturacionId = $this->route('datos_facturacion') ?? $this->route('datosFacturacion');
            $clienteId = $this->input('cliente_id');
            
            // Si no se proporciona cliente_id, obtenerlo del registro actual
            if (!$clienteId && $datosFacturacionId) {
                $datoActual = DatosFacturacion::find($datosFacturacionId);
                $clienteId = $datoActual?->cliente_id;
            }
            
            if ($clienteId) {
                $existePredeterminado = DatosFacturacion::where('cliente_id', $clienteId)
                    ->where('predeterminado', true)
                    ->where('activo', true)
                    ->where('id', '!=', $datosFacturacionId)
                    ->exists();

                if ($existePredeterminado) {
                    $validator->errors()->add('predeterminado', 'Ya existe un dato de facturación predeterminado para este cliente.');
                }
            }
        }
    }

    /**
     * Validar dígito verificador del RUC peruano
     */
    private function validateRucCheckDigit(string $ruc): bool
    {
        $factor = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 10; $i++) {
            $suma += (int)$ruc[$i] * $factor[$i];
        }

        $resto = $suma % 11;
        $digitoVerificador = ($resto < 2) ? $resto : 11 - $resto;

        return (int)$ruc[10] === $digitoVerificador;
    }
}
