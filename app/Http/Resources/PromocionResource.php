<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromocionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo,
            'descuento_porcentaje' => $this->descuento_porcentaje ? (float) $this->descuento_porcentaje : null,
            'descuento_monto' => $this->descuento_monto ? (float) $this->descuento_monto : null,
            'compra_minima' => $this->compra_minima ? (float) $this->compra_minima : null,
            'fecha_inicio' => $this->fecha_inicio?->toISOString(),
            'fecha_fin' => $this->fecha_fin?->toISOString(),
            'activo' => (bool) $this->activo,
            'productos_incluidos' => $this->productos_incluidos,
            'categorias_incluidas' => $this->categorias_incluidas,
            'zonas_aplicables' => $this->zonas_aplicables,
            'limite_uso_total' => $this->limite_uso_total,
            'limite_uso_cliente' => $this->limite_uso_cliente,
            'usos_actuales' => $this->usos_actuales,
            'imagen' => $this->imagen,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada
            'tipo_texto' => $this->getTipoTexto(),
            'descuento_texto' => $this->getDescuentoTexto(),
            'compra_minima_formateada' => $this->compra_minima ? 'S/ ' . number_format((float) $this->compra_minima, 2) : null,
            'fecha_inicio_formateada' => $this->fecha_inicio?->format('d/m/Y H:i'),
            'fecha_fin_formateada' => $this->fecha_fin?->format('d/m/Y H:i'),
            'imagen_url' => $this->imagen ? asset('assets/promociones/' . $this->imagen) : null,
            
            // Estado de la promoción
            'estado_promocion' => [
                'vigente' => $this->esVigente(),
                'disponible' => $this->estaDisponible(),
                'por_comenzar' => $this->esPorComenzar(),
                'expirada' => $this->estaExpirada(),
                'agotada' => $this->estaAgotada(),
                'dias_restantes' => $this->getDiasRestantes(),
                'usos_restantes' => $this->getUsosRestantes(),
                'porcentaje_uso' => $this->getPorcentajeUso(),
            ],

            // Alcance de la promoción
            'alcance' => [
                'productos_count' => $this->getProductosCount(),
                'categorias_count' => $this->getCategoriasCount(),
                'zonas_count' => $this->getZonasCount(),
                'es_promocion_general' => $this->esPromocionGeneral(),
                'es_promocion_especifica' => $this->esPromocionEspecifica(),
            ],

            // Validaciones
            'validaciones' => [
                'tiene_descuento' => $this->tieneDescuento(),
                'fechas_validas' => $this->sonFechasValidas(),
                'limites_configurados' => $this->sonLimitesConfigurados(),
                'configuracion_completa' => $this->esConfiguracionCompleta(),
            ],
        ];
    }

    private function getTipoTexto(): string
    {
        return match($this->tipo) {
            'descuento_producto' => 'Descuento en productos',
            'descuento_categoria' => 'Descuento en categorías',
            '2x1' => 'Promoción 2x1',
            '3x2' => 'Promoción 3x2',
            'envio_gratis' => 'Envío gratis',
            'combo' => 'Combo especial',
            default => 'Tipo de promoción desconocido'
        };
    }

    private function getDescuentoTexto(): string
    {
        if ($this->tipo === 'envio_gratis') {
            return 'Envío gratis';
        }

        if (in_array($this->tipo, ['2x1', '3x2'])) {
            return $this->getTipoTexto();
        }

        if ($this->descuento_porcentaje) {
            return $this->descuento_porcentaje . '% de descuento';
        }

        if ($this->descuento_monto) {
            return 'S/ ' . number_format((float) $this->descuento_monto, 2) . ' de descuento';
        }

        return 'Descuento especial';
    }

    private function esVigente(): bool
    {
        if (!$this->activo) {
            return false;
        }

        $ahora = now();
        
        return (!$this->fecha_inicio || $ahora->gte($this->fecha_inicio)) &&
               (!$this->fecha_fin || $ahora->lte($this->fecha_fin));
    }

    private function estaDisponible(): bool
    {
        return $this->esVigente() && !$this->estaAgotada();
    }

    private function esPorComenzar(): bool
    {
        return $this->activo && $this->fecha_inicio && now()->lt($this->fecha_inicio);
    }

    private function estaExpirada(): bool
    {
        return $this->fecha_fin && now()->gt($this->fecha_fin);
    }

    private function estaAgotada(): bool
    {
        return $this->limite_uso_total && $this->usos_actuales >= $this->limite_uso_total;
    }

    private function getDiasRestantes(): ?int
    {
        if (!$this->fecha_fin || $this->estaExpirada()) {
            return null;
        }

        return now()->diffInDays($this->fecha_fin);
    }

    private function getUsosRestantes(): ?int
    {
        if (!$this->limite_uso_total) {
            return null;
        }

        return max(0, $this->limite_uso_total - $this->usos_actuales);
    }

    private function getPorcentajeUso(): float
    {
        if (!$this->limite_uso_total) {
            return 0.0;
        }

        return ($this->usos_actuales / $this->limite_uso_total) * 100;
    }

    private function getProductosCount(): int
    {
        return $this->productos_incluidos && is_array($this->productos_incluidos) 
            ? count($this->productos_incluidos) 
            : 0;
    }

    private function getCategoriasCount(): int
    {
        return $this->categorias_incluidas && is_array($this->categorias_incluidas) 
            ? count($this->categorias_incluidas) 
            : 0;
    }

    private function getZonasCount(): int
    {
        return $this->zonas_aplicables && is_array($this->zonas_aplicables) 
            ? count($this->zonas_aplicables) 
            : 0;
    }

    private function esPromocionGeneral(): bool
    {
        return $this->getProductosCount() === 0 && $this->getCategoriasCount() === 0;
    }

    private function esPromocionEspecifica(): bool
    {
        return $this->getProductosCount() > 0 || $this->getCategoriasCount() > 0;
    }

    private function tieneDescuento(): bool
    {
        return $this->descuento_porcentaje > 0 || $this->descuento_monto > 0 || 
               in_array($this->tipo, ['2x1', '3x2', 'envio_gratis', 'combo']);
    }

    private function sonFechasValidas(): bool
    {
        if (!$this->fecha_inicio || !$this->fecha_fin) {
            return false;
        }

        return $this->fecha_inicio->lte($this->fecha_fin);
    }

    private function sonLimitesConfigurados(): bool
    {
        return $this->limite_uso_total !== null || $this->limite_uso_cliente !== null;
    }

    private function esConfiguracionCompleta(): bool
    {
        return $this->tieneDescuento() && 
               $this->sonFechasValidas() && 
               !empty($this->nombre) && 
               !empty($this->descripcion);
    }
}