<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CuotaCredito;
use App\Models\Pedido;
use Illuminate\Database\Seeder;

class CuotaCreditoSeeder extends Seeder
{
    public function run(): void
    {
        $pedidosCredito = Pedido::where('tipo_pago', 'credito')
            ->whereNotNull('cuotas')
            ->whereNotNull('monto_cuota')
            ->get();

        foreach ($pedidosCredito as $pedido) {
            $fechaInicio = $pedido->created_at->addDays(30); // Primera cuota a los 30 días
            
            for ($numeroCuota = 1; $numeroCuota <= $pedido->cuotas; $numeroCuota++) {
                $fechaVencimiento = $fechaInicio->copy()->addMonths($numeroCuota - 1);
                
                // Determinar estado de la cuota
                $estado = $this->determinarEstadoCuota($fechaVencimiento, $numeroCuota, $pedido->cuotas);
                
                // Calcular mora si está atrasada
                $mora = 0;
                $fechaPago = null;
                
                if ($estado === 'pagado') {
                    // Si está pagada, calcular fecha de pago
                    $diasAtraso = rand(-5, 10); // Puede pagar antes o después
                    $fechaPago = $fechaVencimiento->copy()->addDays($diasAtraso);
                    
                    if ($diasAtraso > 0) {
                        $mora = $pedido->monto_cuota * 0.05 * $diasAtraso; // 5% de mora por día
                    }
                } elseif ($estado === 'atrasado') {
                    $diasAtraso = now()->diffInDays($fechaVencimiento);
                    $mora = $pedido->monto_cuota * 0.05 * $diasAtraso;
                }
                
                // Calcular interés de la cuota
                $interes = $pedido->interes_total / $pedido->cuotas;
                
                CuotaCredito::create([
                    'pedido_id' => $pedido->id,
                    'numero_cuota' => $numeroCuota,
                    'monto_cuota' => $pedido->monto_cuota,
                    'interes' => $interes,
                    'mora' => $mora,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'fecha_pago' => $fechaPago,
                    'estado' => $estado,
                ]);
            }
        }
    }

    private function determinarEstadoCuota($fechaVencimiento, $numeroCuota, $totalCuotas): string
    {
        $ahora = now();
        
        // Las primeras cuotas tienen mayor probabilidad de estar pagadas
        $probabilidadPago = match (true) {
            $numeroCuota <= 2 => 85,
            $numeroCuota <= 4 => 70,
            $numeroCuota <= 8 => 50,
            default => 30,
        };
        
        // Si la fecha de vencimiento ya pasó
        if ($fechaVencimiento < $ahora) {
            if (rand(1, 100) <= $probabilidadPago) {
                return 'pagado';
            } else {
                return rand(1, 100) <= 70 ? 'atrasado' : 'pagado';
            }
        }
        
        // Si es cuota futura pero cercana (próximos 30 días)
        if ($fechaVencimiento <= $ahora->copy()->addDays(30)) {
            return rand(1, 100) <= 20 ? 'pagado' : 'pendiente'; // 20% pagadas antes
        }
        
        // Cuotas futuras
        return 'pendiente';
    }
} 