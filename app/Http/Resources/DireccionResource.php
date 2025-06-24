<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DireccionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'distrito_id' => $this->distrito_id,
            'direccion' => $this->direccion,
            'referencia' => $this->referencia,
            'codigo_postal' => $this->codigo_postal,
            'numero_exterior' => $this->numero_exterior,
            'numero_interior' => $this->numero_interior,
            'urbanizacion' => $this->urbanizacion,
            'etapa' => $this->etapa,
            'manzana' => $this->manzana,
            'lote' => $this->lote,
            'latitud' => $this->latitud ? (float) $this->latitud : null,
            'longitud' => $this->longitud ? (float) $this->longitud : null,
            'predeterminada' => (bool) $this->predeterminada,
            'validada' => (bool) $this->validada,
            'alias' => $this->alias,
            'instrucciones_entrega' => $this->instrucciones_entrega,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Campos legacy para compatibilidad
            'distrito' => $this->whenLoaded('distrito', fn() => $this->distrito->nombre),
            'provincia' => $this->whenLoaded('distrito.provincia', fn() => $this->distrito->provincia->nombre),
            'departamento' => $this->whenLoaded('distrito.provincia.departamento', fn() => $this->distrito->provincia->departamento->nombre),
            'pais' => $this->whenLoaded('distrito.provincia.departamento', fn() => $this->distrito->provincia->departamento->pais ?? 'Perú'),

            // Información calculada
            'tiene_coordenadas' => $this->tieneCoordendas(),
            'direccion_completa' => $this->getDireccionCompleta(),
            'direccion_completa_detallada' => $this->getDireccionCompletaDetallada(),
            'coordenadas' => $this->getCoordenadas(),
            'alias_formateado' => $this->alias ?: 'Sin alias',
            'numero_completo' => $this->getNumeroCompleto(),

            // Información del distrito con relaciones
            'distrito_detalle' => $this->whenLoaded('distrito', function () {
                return [
                    'id' => $this->distrito->id,
                    'nombre' => $this->distrito->nombre,
                    'codigo' => $this->distrito->codigo,
                    'codigo_postal' => $this->distrito->codigo_postal,
                    'disponible_delivery' => (bool) $this->distrito->disponible_delivery,
                    'provincia' => $this->distrito->relationLoaded('provincia') ? [
                        'id' => $this->distrito->provincia->id,
                        'nombre' => $this->distrito->provincia->nombre,
                        'departamento' => $this->distrito->provincia->relationLoaded('departamento') ? [
                            'id' => $this->distrito->provincia->departamento->id,
                            'nombre' => $this->distrito->provincia->departamento->nombre,
                            'pais' => $this->distrito->provincia->departamento->pais ?? 'Perú',
                        ] : null,
                    ] : null,
                ];
            }),

            // Usuario propietario
            'usuario' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'nombre' => $this->user->nombre ?? $this->user->name,
                    'email' => $this->user->email,
                ];
            }),

            // Relación con direccion validada
            'direccion_validada' => $this->whenLoaded('direccionValidada', function () {
                if (!$this->direccionValidada) return null;
                
                return [
                    'id' => $this->direccionValidada->id,
                    'zona_reparto_id' => $this->direccionValidada->zona_reparto_id,
                    'en_zona_cobertura' => (bool) $this->direccionValidada->en_zona_cobertura,
                    'costo_envio_calculado' => (float) $this->direccionValidada->costo_envio_calculado,
                    'distancia_tienda_km' => $this->direccionValidada->distancia_tienda_km ? (float) $this->direccionValidada->distancia_tienda_km : null,
                    'zona_reparto' => $this->direccionValidada->relationLoaded('zonaReparto') ? [
                        'id' => $this->direccionValidada->zonaReparto->id,
                        'nombre' => $this->direccionValidada->zonaReparto->nombre,
                    ] : null,
                ];
            }),

            // Validaciones de la dirección
            'validaciones' => [
                'es_direccion_completa' => $this->esDireccionCompleta(),
                'tiene_referencias_claras' => !empty($this->referencia),
                'tiene_instrucciones_entrega' => !empty($this->instrucciones_entrega),
                'esta_geolocalizada' => $this->tieneCoordendas(),
                'esta_validada' => (bool) $this->validada,
                'es_predeterminada' => (bool) $this->predeterminada,
                'tiene_numeracion' => $this->tieneNumeracion(),
                'direccion_valida_delivery' => $this->esValidaParaDelivery(),
            ],

            // Ubicación legacy para compatibilidad
            'ubicacion' => [
                'distrito' => $this->whenLoaded('distrito', fn() => $this->distrito->nombre),
                'provincia' => $this->whenLoaded('distrito.provincia', fn() => $this->distrito->provincia->nombre),
                'departamento' => $this->whenLoaded('distrito.provincia.departamento', fn() => $this->distrito->provincia->departamento->nombre),
                'pais' => $this->whenLoaded('distrito.provincia.departamento', fn() => $this->distrito->provincia->departamento->pais ?? 'Perú'),
            ],
        ];
    }

    private function tieneCoordendas(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    private function getCoordenadas(): ?array
    {
        if (!$this->tieneCoordendas()) {
            return null;
        }

        return [
            'lat' => (float) $this->latitud,
            'lng' => (float) $this->longitud,
        ];
    }

    private function getNumeroCompleto(): ?string
    {
        $partes = array_filter([
            $this->numero_exterior,
            $this->numero_interior ? "Int. {$this->numero_interior}" : null,
        ]);

        return !empty($partes) ? implode(' ', $partes) : null;
    }

    private function getDireccionCompleta(): string
    {
        $partes = array_filter([
            $this->direccion,
            $this->getNumeroCompleto(),
            $this->urbanizacion,
            $this->etapa ? "Etapa {$this->etapa}" : null,
            $this->manzana && $this->lote ? "Mz. {$this->manzana} Lt. {$this->lote}" : null,
            $this->referencia ? "Ref: {$this->referencia}" : null,
        ]);

        $direccionBase = implode(', ', $partes);

        // Agregar ubicación política si está cargada
        if ($this->relationLoaded('distrito')) {
            $ubicacion = array_filter([
                $this->distrito->nombre,
                $this->distrito->relationLoaded('provincia') ? $this->distrito->provincia->nombre : null,
                $this->distrito->relationLoaded('provincia.departamento') ? $this->distrito->provincia->departamento->nombre : null,
            ]);

            if (!empty($ubicacion)) {
                $direccionBase .= ' - ' . implode(', ', $ubicacion);
            }
        }

        return $direccionBase;
    }

    private function getDireccionCompletaDetallada(): array
    {
        return [
            'direccion_principal' => $this->direccion,
            'numeracion' => $this->getNumeroCompleto(),
            'ubicacion_especifica' => array_filter([
                'urbanizacion' => $this->urbanizacion,
                'etapa' => $this->etapa,
                'manzana' => $this->manzana,
                'lote' => $this->lote,
            ]),
            'referencia' => $this->referencia,
            'instrucciones_entrega' => $this->instrucciones_entrega,
            'alias' => $this->alias,
            'codigo_postal' => $this->codigo_postal,
        ];
    }

    private function esDireccionCompleta(): bool
    {
        return !empty($this->direccion) && 
               $this->distrito_id !== null &&
               (!empty($this->referencia) || $this->tieneNumeracion());
    }

    private function tieneNumeracion(): bool
    {
        return !empty($this->numero_exterior) || 
               (!empty($this->manzana) && !empty($this->lote));
    }

    private function esValidaParaDelivery(): bool
    {
        if (!$this->relationLoaded('distrito')) {
            return false;
        }

        return $this->esDireccionCompleta() && 
               (bool) $this->distrito->disponible_delivery;
    }
} 