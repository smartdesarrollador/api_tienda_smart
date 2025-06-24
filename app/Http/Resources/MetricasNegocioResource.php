<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetricasNegocioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha,
            'pedidos_totales' => $this->pedidos_totales,
            'pedidos_entregados' => $this->pedidos_entregados,
            'pedidos_cancelados' => $this->pedidos_cancelados,
            'ventas_totales' => (float) $this->ventas_totales,
            'costo_envios' => (float) $this->costo_envios,
            'nuevos_clientes' => $this->nuevos_clientes,
            'clientes_recurrentes' => $this->clientes_recurrentes,
            'tiempo_promedio_entrega' => (float) $this->tiempo_promedio_entrega,
            'productos_vendidos' => $this->productos_vendidos,
            'ticket_promedio' => (float) $this->ticket_promedio,
            'productos_mas_vendidos' => $this->productos_mas_vendidos,
            'zonas_mas_activas' => $this->zonas_mas_activas,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Información calculada y formateada
            'fecha_formateada' => \Carbon\Carbon::parse($this->fecha)->format('d/m/Y'),
            'ventas_totales_formateadas' => 'S/ ' . number_format((float) $this->ventas_totales, 2),
            'costo_envios_formateado' => 'S/ ' . number_format((float) $this->costo_envios, 2),
            'ticket_promedio_formateado' => 'S/ ' . number_format((float) $this->ticket_promedio, 2),
            'tiempo_promedio_entrega_texto' => $this->getTiempoPromedioTexto(),

            // Porcentajes y ratios
            'metricas_porcentuales' => [
                'tasa_entrega' => $this->getTasaEntrega(),
                'tasa_cancelacion' => $this->getTasaCancelacion(),
                'porcentaje_nuevos_clientes' => $this->getPorcentajeNuevosClientes(),
                'porcentaje_clientes_recurrentes' => $this->getPorcentajeClientesRecurrentes(),
                'margen_envios' => $this->getMargenEnvios(),
            ],

            // Promedios y KPIs
            'kpis' => [
                'pedidos_por_cliente_nuevo' => $this->getPedidosPorClienteNuevo(),
                'ventas_por_pedido_entregado' => $this->getVentasPorPedidoEntregado(),
                'productos_por_pedido' => $this->getProductosPorPedido(),
                'eficiencia_entrega' => $this->getEficienciaEntrega(),
                'rentabilidad_dia' => $this->getRentabilidadDia(),
            ],

            // Comparaciones y tendencias
            'comparaciones' => [
                'es_dia_exitoso' => $this->esDiaExitoso(),
                'supera_ticket_promedio' => $this->superaTicketPromedio(),
                'tiempo_entrega_optimo' => $this->esTiempoEntregaOptimo(),
                'volumen_alto' => $this->esVolumenAlto(),
            ],

            // Top productos y zonas
            'tops' => [
                'top_3_productos' => $this->getTop3Productos(),
                'top_3_zonas' => $this->getTop3Zonas(),
                'productos_vendidos_diferentes' => $this->getProductosVendidosDiferentes(),
                'zonas_activas_count' => $this->getZonasActivasCount(),
            ],
        ];
    }

    private function getTiempoPromedioTexto(): string
    {
        $minutos = (float) $this->tiempo_promedio_entrega;
        
        if ($minutos < 60) {
            return round($minutos) . ' minutos';
        }

        $horas = floor($minutos / 60);
        $minutosRestantes = $minutos % 60;

        if ($minutosRestantes == 0) {
            return $horas . ' hora' . ($horas > 1 ? 's' : '');
        }

        return $horas . 'h ' . round($minutosRestantes) . 'min';
    }

    private function getTasaEntrega(): float
    {
        return $this->pedidos_totales > 0 
            ? round(($this->pedidos_entregados / $this->pedidos_totales) * 100, 2)
            : 0.0;
    }

    private function getTasaCancelacion(): float
    {
        return $this->pedidos_totales > 0 
            ? round(($this->pedidos_cancelados / $this->pedidos_totales) * 100, 2)
            : 0.0;
    }

    private function getPorcentajeNuevosClientes(): float
    {
        $totalClientes = $this->nuevos_clientes + $this->clientes_recurrentes;
        return $totalClientes > 0 
            ? round(($this->nuevos_clientes / $totalClientes) * 100, 2)
            : 0.0;
    }

    private function getPorcentajeClientesRecurrentes(): float
    {
        $totalClientes = $this->nuevos_clientes + $this->clientes_recurrentes;
        return $totalClientes > 0 
            ? round(($this->clientes_recurrentes / $totalClientes) * 100, 2)
            : 0.0;
    }

    private function getMargenEnvios(): float
    {
        return $this->ventas_totales > 0 
            ? round(($this->costo_envios / $this->ventas_totales) * 100, 2)
            : 0.0;
    }

    private function getPedidosPorClienteNuevo(): float
    {
        return $this->nuevos_clientes > 0 
            ? round($this->pedidos_totales / $this->nuevos_clientes, 2)
            : 0.0;
    }

    private function getVentasPorPedidoEntregado(): float
    {
        return $this->pedidos_entregados > 0 
            ? round($this->ventas_totales / $this->pedidos_entregados, 2)
            : 0.0;
    }

    private function getProductosPorPedido(): float
    {
        return $this->pedidos_totales > 0 
            ? round($this->productos_vendidos / $this->pedidos_totales, 2)
            : 0.0;
    }

    private function getEficienciaEntrega(): string
    {
        $tasa = $this->getTasaEntrega();
        
        return match(true) {
            $tasa >= 90 => 'Excelente',
            $tasa >= 80 => 'Buena',
            $tasa >= 70 => 'Regular',
            default => 'Necesita mejora'
        };
    }

    private function getRentabilidadDia(): float
    {
        return (float) ($this->ventas_totales - $this->costo_envios);
    }

    private function esDiaExitoso(): bool
    {
        return $this->getTasaEntrega() >= 85 && 
               $this->getTasaCancelacion() <= 10 && 
               $this->ventas_totales > 0;
    }

    private function superaTicketPromedio(): bool
    {
        // Asumir un ticket promedio objetivo de S/50
        return $this->ticket_promedio >= 50.0;
    }

    private function esTiempoEntregaOptimo(): bool
    {
        // Tiempo óptimo menor a 45 minutos
        return $this->tiempo_promedio_entrega <= 45.0;
    }

    private function esVolumenAlto(): bool
    {
        // Volumen alto: más de 20 pedidos al día
        return $this->pedidos_totales >= 20;
    }

    private function getTop3Productos(): array
    {
        if (!$this->productos_mas_vendidos || !is_array($this->productos_mas_vendidos)) {
            return [];
        }

        return array_slice($this->productos_mas_vendidos, 0, 3);
    }

    private function getTop3Zonas(): array
    {
        if (!$this->zonas_mas_activas || !is_array($this->zonas_mas_activas)) {
            return [];
        }

        return array_slice($this->zonas_mas_activas, 0, 3);
    }

    private function getProductosVendidosDiferentes(): int
    {
        if (!$this->productos_mas_vendidos || !is_array($this->productos_mas_vendidos)) {
            return 0;
        }

        return count($this->productos_mas_vendidos);
    }

    private function getZonasActivasCount(): int
    {
        if (!$this->zonas_mas_activas || !is_array($this->zonas_mas_activas)) {
            return 0;
        }

        return count($this->zonas_mas_activas);
    }
} 