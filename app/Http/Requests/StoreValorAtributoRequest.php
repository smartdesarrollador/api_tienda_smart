<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Atributo;

class StoreValorAtributoRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'atributo_id' => [
                'required',
                'integer',
                'exists:atributos,id'
            ],
            'valor' => [
                'required',
                'string',
                'max:255',
                Rule::unique('valores_atributo')->where(function ($query) {
                    return $query->where('atributo_id', $this->atributo_id);
                })
            ],
            'codigo' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9#_-]+$/',
                Rule::unique('valores_atributo')->where(function ($query) {
                    return $query->where('atributo_id', $this->atributo_id)
                                 ->whereNotNull('codigo');
                })
            ],
            'imagen' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:2048' // 2MB máximo
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->atributo_id) {
                $atributo = Atributo::find($this->atributo_id);
                
                if ($atributo) {
                    // Validaciones específicas según el tipo de atributo
                    $this->validateByAttributeType($validator, $atributo);
                }
            }
        });
    }

    /**
     * Validaciones específicas según el tipo de atributo
     */
    private function validateByAttributeType($validator, Atributo $atributo): void
    {
        switch ($atributo->tipo) {
            case 'color':
                // Para colores, el código debe ser un color hexadecimal válido
                if ($this->codigo && !preg_match('/^#[0-9A-Fa-f]{6}$/', $this->codigo)) {
                    $validator->errors()->add('codigo', 'Para atributos de color, el código debe ser un color hexadecimal válido (ej: #FF0000)');
                }
                break;
                
            case 'numero':
                // Para números, el valor debe ser numérico
                if (!is_numeric($this->valor)) {
                    $validator->errors()->add('valor', 'Para atributos numéricos, el valor debe ser un número válido');
                }
                break;
                
            case 'tamaño':
                // Para tamaños, normalizar valores comunes
                $tallaPattern = '/^(XXS|XS|S|M|L|XL|XXL|XXXL|\d+(\.\d+)?|\d+\/\d+)$/i';
                if (!preg_match($tallaPattern, $this->valor)) {
                    $validator->errors()->add('valor', 'Para atributos de tamaño, usar valores como: XS, S, M, L, XL, números o fracciones');
                }
                break;
                
            case 'booleano':
                // Para booleanos, solo permitir valores específicos
                $booleanValues = ['Sí', 'No', 'Verdadero', 'Falso', 'Activado', 'Desactivado', '1', '0'];
                if (!in_array($this->valor, $booleanValues)) {
                    $validator->errors()->add('valor', 'Para atributos booleanos, usar valores como: Sí, No, Verdadero, Falso');
                }
                break;
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalizar el valor según el tipo de atributo
        if ($this->has('valor') && $this->atributo_id) {
            $atributo = Atributo::find($this->atributo_id);
            
            if ($atributo) {
                $valor = $this->valor;
                
                switch ($atributo->tipo) {
                    case 'tamaño':
                        $valor = strtoupper(trim($valor));
                        break;
                    case 'color':
                        $valor = ucfirst(strtolower(trim($valor)));
                        break;
                    default:
                        $valor = trim($valor);
                        break;
                }
                
                $this->merge(['valor' => $valor]);
            }
        }

        // Normalizar código de color si es necesario
        if ($this->has('codigo') && $this->codigo) {
            $codigo = strtoupper(trim($this->codigo));
            
            // Si es un color y no empieza con #, agregarlo
            if ($this->atributo_id) {
                $atributo = Atributo::find($this->atributo_id);
                if ($atributo && $atributo->tipo === 'color' && !str_starts_with($codigo, '#')) {
                    $codigo = '#' . $codigo;
                }
            }
            
            $this->merge(['codigo' => $codigo]);
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'atributo_id.required' => 'El ID del atributo es obligatorio.',
            'atributo_id.exists' => 'El atributo especificado no existe.',
            'valor.required' => 'El valor es obligatorio.',
            'valor.unique' => 'Este valor ya existe para el atributo seleccionado.',
            'valor.max' => 'El valor no puede exceder los 255 caracteres.',
            'codigo.unique' => 'Este código ya existe para el atributo seleccionado.',
            'codigo.max' => 'El código no puede exceder los 50 caracteres.',
            'codigo.regex' => 'El código solo puede contener letras, números, #, guiones y guiones bajos.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.mimes' => 'La imagen debe ser de tipo: jpeg, jpg, png, gif o webp.',
            'imagen.max' => 'La imagen no puede ser mayor a 2MB.',
        ];
    }
} 