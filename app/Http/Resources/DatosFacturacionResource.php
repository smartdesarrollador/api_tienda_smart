<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\DatosFacturacion;

class DatosFacturacionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            
            // Información del documento
            'tipo_documento' => $this->tipo_documento,
            'numero_documento' => $this->numero_documento,
            'numero_documento_formateado' => $this->formatearNumeroDocumento(),
            
            // Nombres y razón social
            'nombre_facturacion' => $this->nombre_facturacion,
            'razon_social' => $this->razon_social,
            'nombre_facturacion_completo' => $this->nombre_facturacion_completo,
            
            // Dirección fiscal
            'direccion_fiscal' => $this->direccion_fiscal,
            'distrito_fiscal' => $this->distrito_fiscal,
            'provincia_fiscal' => $this->provincia_fiscal,
            'departamento_fiscal' => $this->departamento_fiscal,
            'codigo_postal_fiscal' => $this->codigo_postal_fiscal,
            'direccion_fiscal_completa' => $this->direccion_fiscal_completa,
            
            // Contacto
            'telefono_fiscal' => $this->telefono_fiscal,
            'email_facturacion' => $this->email_facturacion,
            
            // Estados
            'predeterminado' => $this->predeterminado,
            'activo' => $this->activo,
            'is_predeterminado' => $this->isPredeterminado(),
            'is_activo' => $this->isActivo(),
            
            // Datos empresariales (cuando aplique)
            'contacto_empresa' => $this->contacto_empresa,
            'giro_negocio' => $this->giro_negocio,
            
            // Metadata adicional
            'datos_adicionales' => $this->datos_adicionales,
            
            // Accessors del modelo
            'is_empresa' => $this->is_empresa,
            'is_persona_natural' => $this->is_persona_natural,
            
            // Validaciones
            'documento_valido' => $this->validarNumeroDocumento(),
            
            // Información del tipo de documento
            'tipo_documento_info' => $this->getTipoDocumentoInfo(),
            
            // Cliente relacionado (solo cuando se carga)
            'cliente' => $this->whenLoaded('cliente', function () {
                return [
                    'id' => $this->cliente->id,
                    'nombre_completo' => $this->cliente->nombre_completo,
                    'dni' => $this->cliente->dni,
                    'telefono' => $this->cliente->telefono,
                    'estado' => $this->cliente->estado,
                ];
            }),
            
            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Datos para facturación (formato específico para comprobantes)
            'datos_facturacion_comprobante' => $this->when($request->has('para_comprobante'), function () {
                return $this->toFacturacionArray();
            }),
            
            // Información adicional contextual
            'resumen' => $this->when($request->has('incluir_resumen'), function () {
                return [
                    'tipo_persona' => $this->is_empresa ? 'Jurídica' : 'Natural',
                    'documento_descripcion' => $this->getDescripcionTipoDocumento(),
                    'estado_texto' => $this->getEstadoTexto(),
                    'tiempo_registro' => $this->created_at?->diffForHumans(),
                ];
            }),
        ];
    }

    /**
     * Formatear número de documento según tipo
     */
    private function formatearNumeroDocumento(): string
    {
        switch ($this->tipo_documento) {
            case DatosFacturacion::TIPO_DNI:
                return $this->numero_documento;
            case DatosFacturacion::TIPO_RUC:
                return substr($this->numero_documento, 0, 2) . '-' . 
                       substr($this->numero_documento, 2, 8) . '-' . 
                       substr($this->numero_documento, 10, 1);
            case DatosFacturacion::TIPO_PASAPORTE:
                return strtoupper($this->numero_documento);
            default:
                return $this->numero_documento;
        }
    }

    /**
     * Obtener información del tipo de documento
     */
    private function getTipoDocumentoInfo(): array
    {
        $info = [
            DatosFacturacion::TIPO_DNI => [
                'nombre' => 'DNI',
                'descripcion' => 'Documento Nacional de Identidad',
                'longitud' => 8,
                'tipo_persona' => 'Natural',
            ],
            DatosFacturacion::TIPO_RUC => [
                'nombre' => 'RUC',
                'descripcion' => 'Registro Único de Contribuyentes',
                'longitud' => 11,
                'tipo_persona' => 'Jurídica',
            ],
            DatosFacturacion::TIPO_PASAPORTE => [
                'nombre' => 'Pasaporte',
                'descripcion' => 'Pasaporte',
                'longitud' => 'Variable',
                'tipo_persona' => 'Natural',
            ],
            DatosFacturacion::TIPO_CARNET_EXTRANJERIA => [
                'nombre' => 'Carnet de Extranjería',
                'descripcion' => 'Carnet de Extranjería',
                'longitud' => 9,
                'tipo_persona' => 'Natural',
            ],
        ];

        return $info[$this->tipo_documento] ?? [
            'nombre' => 'Desconocido',
            'descripcion' => 'Tipo de documento no reconocido',
            'longitud' => 'Variable',
            'tipo_persona' => 'Desconocido',
        ];
    }

    /**
     * Obtener descripción del tipo de documento
     */
    private function getDescripcionTipoDocumento(): string
    {
        $descripciones = [
            DatosFacturacion::TIPO_DNI => 'Documento Nacional de Identidad',
            DatosFacturacion::TIPO_RUC => 'Registro Único de Contribuyentes',
            DatosFacturacion::TIPO_PASAPORTE => 'Pasaporte',
            DatosFacturacion::TIPO_CARNET_EXTRANJERIA => 'Carnet de Extranjería',
        ];

        return $descripciones[$this->tipo_documento] ?? 'Tipo de documento desconocido';
    }

    /**
     * Obtener estado en texto
     */
    private function getEstadoTexto(): string
    {
        if (!$this->activo) {
            return 'Inactivo';
        }

        return $this->predeterminado ? 'Activo (Predeterminado)' : 'Activo';
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'tipos_documento_disponibles' => DatosFacturacion::TIPOS_DOCUMENTO,
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'DatosFacturacion');
    }
}
