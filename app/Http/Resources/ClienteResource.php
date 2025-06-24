<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
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
            
            // Datos básicos del cliente
            'user_id' => $this->user_id,
            'dni' => $this->dni,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
            'nombre_completo' => $this->nombre_completo,
            'apellidos' => $this->apellidos,
            'fecha_nacimiento' => $this->fecha_nacimiento?->format('Y-m-d'),
            'genero' => $this->genero,
            
            // Datos financieros
            'limite_credito' => (float) $this->limite_credito,
            'credito_disponible' => $this->getCreditoDisponible(),
            'tiene_credito' => $this->tiene_credito,
            
            // Estados y verificaciones
            'verificado' => $this->verificado,
            'estado' => $this->estado,
            'is_activo' => $this->isActivo(),
            'is_verificado' => $this->isVerificado(),
            
            // Datos profesionales
            'referido_por' => $this->referido_por,
            'profesion' => $this->profesion,
            'empresa' => $this->empresa,
            'ingresos_mensuales' => $this->when($this->ingresos_mensuales, (float) $this->ingresos_mensuales),
            
            // Datos adicionales
            'preferencias' => $this->preferencias,
            'metadata' => $this->metadata,
            
            // Accessors del modelo
            'nombre_completo_formateado' => $this->nombre_completo_formateado,
            'edad' => $this->edad,
            
            // Información del usuario relacionado
            'usuario' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'rol' => $this->user->rol,
                    'avatar' => $this->user->avatar,
                    'ultimo_login' => $this->user->ultimo_login?->format('Y-m-d H:i:s'),
                    'email_verified_at' => $this->user->email_verified_at?->format('Y-m-d H:i:s'),
                ];
            }),
            
            // Datos de facturación
            'datos_facturacion' => DatosFacturacionResource::collection($this->whenLoaded('datosFacturacion')),
            'dato_facturacion_predeterminado' => new DatosFacturacionResource($this->whenLoaded('datoFacturacionPredeterminado')),
            'datos_facturacion_activos' => DatosFacturacionResource::collection($this->whenLoaded('datosFacturacionActivos')),
            
            // Contadores y estadísticas
            'total_datos_facturacion' => $this->whenCounted('datosFacturacion'),
            'datos_facturacion_activos_count' => $this->whenCounted('datosFacturacionActivos'),
            
            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Información adicional contextual
            'resumen' => $this->when($request->has('incluir_resumen'), function () {
                return [
                    'edad_anos' => $this->edad,
                    'tiempo_como_cliente' => $this->created_at?->diffForHumans(),
                    'estado_credito' => $this->limite_credito > 0 ? 'Con crédito' : 'Sin crédito',
                    'estado_verificacion' => $this->verificado ? 'Verificado' : 'Pendiente verificación',
                    'origen_registro' => $this->metadata['fuente_registro'] ?? 'Desconocido',
                ];
            }),
        ];
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
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'Cliente');
    }
}
