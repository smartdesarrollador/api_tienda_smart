<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrupoAdicionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'obligatorio' => (bool) $this->obligatorio,
            'multiple_seleccion' => (bool) $this->multiple_seleccion,
            'minimo_seleccion' => $this->minimo_seleccion,
            'maximo_seleccion' => $this->maximo_seleccion,
            'orden' => $this->orden,
            'activo' => (bool) $this->activo,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'tipo_seleccion' => $this->getTipoSeleccion(),
            'reglas_seleccion' => $this->getReglasSeleccion(),
            'seleccion_texto' => $this->getSeleccionTexto(),

            // Relaciones
            'adicionales' => $this->whenLoaded('adicionales', function () {
                return $this->adicionales->map(function ($adicional) {
                    return [
                        'id' => $adicional->id,
                        'nombre' => $adicional->nombre,
                        'slug' => $adicional->slug,
                        'descripcion' => $adicional->descripcion,
                        'precio' => (float) $adicional->precio,
                        'imagen' => $adicional->imagen,
                        'icono' => $adicional->icono,
                        'tipo' => $adicional->tipo,
                        'disponible' => (bool) $adicional->disponible,
                        'activo' => (bool) $adicional->activo,
                        'stock' => $adicional->stock,
                        'vegetariano' => (bool) $adicional->vegetariano,
                        'vegano' => (bool) $adicional->vegano,
                        'imagen_url' => $adicional->imagen ? asset('assets/adicionales/' . $adicional->imagen) : null,
                        'icono_url' => $adicional->icono ? asset('assets/iconos/' . $adicional->icono) : null,
                        'pivot' => [
                            'orden' => $adicional->pivot->orden ?? 0,
                        ],
                    ];
                });
            }),

            'productos' => $this->whenLoaded('productos', function () {
                return $this->productos->map(function ($producto) {
                    return [
                        'id' => $producto->id,
                        'nombre' => $producto->nombre,
                        'sku' => $producto->sku,
                        'activo' => (bool) $producto->activo,
                        'pivot' => [
                            'orden' => $producto->pivot->orden ?? 0,
                        ],
                    ];
                });
            }),

            // Estadísticas
            'estadisticas' => [
                'total_adicionales' => $this->whenLoaded('adicionales', function () {
                    return $this->adicionales->count();
                }),
                'adicionales_disponibles' => $this->whenLoaded('adicionales', function () {
                    return $this->adicionales->where('disponible', true)->where('activo', true)->count();
                }),
                'productos_asociados' => $this->whenLoaded('productos', function () {
                    return $this->productos->count();
                }),
            ],
        ];
    }

    private function getTipoSeleccion(): string
    {
        if (!$this->multiple_seleccion) {
            return 'seleccion_unica';
        }

        return 'seleccion_multiple';
    }

    private function getReglasSeleccion(): array
    {
        return [
            'obligatorio' => (bool) $this->obligatorio,
            'multiple_seleccion' => (bool) $this->multiple_seleccion,
            'minimo_seleccion' => $this->minimo_seleccion ?? 0,
            'maximo_seleccion' => $this->maximo_seleccion,
        ];
    }

    private function getSeleccionTexto(): string
    {
        if (!$this->multiple_seleccion) {
            return $this->obligatorio ? 'Seleccionar 1 opción (obligatorio)' : 'Seleccionar 1 opción (opcional)';
        }

        $texto = 'Seleccionar ';
        
        if ($this->minimo_seleccion && $this->maximo_seleccion) {
            if ($this->minimo_seleccion === $this->maximo_seleccion) {
                $texto .= "exactamente {$this->minimo_seleccion} opciones";
            } else {
                $texto .= "entre {$this->minimo_seleccion} y {$this->maximo_seleccion} opciones";
            }
        } elseif ($this->minimo_seleccion) {
            $texto .= "mínimo {$this->minimo_seleccion} opciones";
        } elseif ($this->maximo_seleccion) {
            $texto .= "máximo {$this->maximo_seleccion} opciones";
        } else {
            $texto .= 'múltiples opciones';
        }

        return $texto . ($this->obligatorio ? ' (obligatorio)' : ' (opcional)');
    }
} 