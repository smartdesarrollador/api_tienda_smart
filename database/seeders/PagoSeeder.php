<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\MetodoPago;
use Illuminate\Database\Seeder;

class PagoSeeder extends Seeder
{
    public function run(): void
    {
        $pedidos = Pedido::with('metodoPago')->get();

        foreach ($pedidos as $pedido) {
            // Solo pedidos al contado o entregados tienen pagos registrados
            if (in_array($pedido->tipo_pago, ['contado', 'transferencia', 'tarjeta', 'yape', 'plin']) 
                || $pedido->estado === 'entregado') {
                
                if ($pedido->tipo_pago === 'credito') {
                    // Para crédito, crear pagos de cuotas pagadas
                    $this->crearPagosCuotasCredito($pedido);
                } else {
                    // Para contado, un solo pago del total
                    $this->crearPagoContado($pedido);
                }
            }
        }
    }

    private function crearPagoContado($pedido): void
    {
        $estado = match ($pedido->estado) {
            'entregado', 'enviado' => 'pagado',
            'aprobado', 'en_proceso' => 'pagado',
            'pendiente' => rand(1, 100) <= 70 ? 'pagado' : 'pendiente',
            default => 'pendiente',
        };

        $fechaPago = $estado === 'pagado' 
            ? $pedido->created_at->addHours(rand(1, 48))
            : $pedido->created_at->addDays(rand(1, 5));

        // Calcular comisión usando el método de pago
        $comision = $pedido->metodoPago?->calcularComision((float) $pedido->total) ?? 0;

        Pago::create([
            'pedido_id' => $pedido->id,
            'metodo_pago_id' => $pedido->metodo_pago_id,
            'monto' => $pedido->total,
            'comision' => $comision,
            'numero_cuota' => null,
            'fecha_pago' => $fechaPago,
            'estado' => $estado,
            'metodo' => $this->obtenerMetodoPago($pedido->tipo_pago),
            'referencia' => $this->generarReferencia($pedido->tipo_pago),
            'moneda' => $pedido->moneda ?? 'PEN',
            'respuesta_proveedor' => $this->generarRespuestaProveedor($pedido->tipo_pago, $estado),
            'codigo_autorizacion' => $estado === 'pagado' ? $this->generarCodigoAutorizacion($pedido->tipo_pago) : null,
            'observaciones' => $this->generarObservacionesPago($pedido->tipo_pago, $estado),
        ]);
    }

    private function crearPagosCuotasCredito($pedido): void
    {
        if (!$pedido->cuotas || !$pedido->monto_cuota) {
            return;
        }

        // Crear pagos para las primeras cuotas (simulando historial)
        $cuotasPagadas = rand(1, min(3, $pedido->cuotas)); // Máximo 3 cuotas pagadas

        for ($i = 1; $i <= $cuotasPagadas; $i++) {
            $fechaPago = $pedido->created_at->addMonths($i);
            $estado = rand(1, 100) <= 90 ? 'pagado' : 'atrasado';
            $metodoPagoCuota = collect(['yape', 'plin', 'transferencia-bcp'])->random();

            // Calcular comisión para la cuota
            $comision = $pedido->metodoPago?->calcularComision((float) $pedido->monto_cuota) ?? 0;

            Pago::create([
                'pedido_id' => $pedido->id,
                'metodo_pago_id' => $pedido->metodo_pago_id,
                'monto' => $pedido->monto_cuota,
                'comision' => $comision,
                'numero_cuota' => $i,
                'fecha_pago' => $fechaPago,
                'estado' => $estado,
                'metodo' => collect(['transferencia', 'efectivo', 'yape', 'plin'])->random(),
                'referencia' => 'CUOTA-' . $pedido->id . '-' . $i,
                'moneda' => $pedido->moneda ?? 'PEN',
                'respuesta_proveedor' => $this->generarRespuestaProveedor($metodoPagoCuota, $estado),
                'codigo_autorizacion' => $estado === 'pagado' ? $this->generarCodigoAutorizacion($metodoPagoCuota) : null,
                'observaciones' => 'Pago de cuota ' . $i . ' de ' . $pedido->cuotas,
            ]);
        }
    }

    private function obtenerMetodoPago(string $tipoPago): string
    {
        return match ($tipoPago) {
            'contado' => 'efectivo',
            'transferencia' => 'transferencia_bancaria',
            'tarjeta' => 'tarjeta_credito',
            'yape' => 'yape',
            'plin' => 'plin',
            'credito' => 'cuota',
            default => 'efectivo',
        };
    }

    private function generarReferencia(string $tipoPago): string
    {
        return match ($tipoPago) {
            'yape' => 'YAPE-' . rand(100000, 999999),
            'plin' => 'PLIN-' . rand(100000, 999999),
            'transferencia' => 'TRANS-' . rand(1000000, 9999999),
            'tarjeta' => 'CARD-' . rand(100000, 999999),
            default => 'REF-' . rand(100000, 999999),
        };
    }

    private function generarRespuestaProveedor(string $tipoPago, string $estado): ?array
    {
        if ($estado !== 'pagado') {
            return null;
        }

        return match ($tipoPago) {
            'yape' => [
                'id_transaccion' => 'YP' . rand(1000000, 9999999),
                'estado' => 'SUCCESS',
                'fecha_procesamiento' => now()->toISOString(),
                'mensaje' => 'Pago procesado exitosamente',
                'metodo' => 'YAPE',
            ],
            'plin' => [
                'transaction_id' => 'PL' . rand(1000000, 9999999),
                'status' => 'COMPLETED',
                'processed_at' => now()->toISOString(),
                'message' => 'Payment successful',
                'gateway' => 'PLIN',
            ],
            'visa', 'mastercard', 'tarjeta' => [
                'transaction_id' => 'TX' . rand(100000000, 999999999),
                'authorization_code' => rand(100000, 999999),
                'status' => 'APPROVED',
                'card_brand' => $tipoPago === 'mastercard' ? 'MASTERCARD' : 'VISA',
                'card_last_four' => rand(1000, 9999),
                'processed_at' => now()->toISOString(),
                'gateway_response' => 'APPROVED',
            ],
            'transferencia-bcp', 'transferencia-interbank', 'transferencia' => [
                'numero_operacion' => rand(10000000, 99999999),
                'banco_origen' => $tipoPago === 'transferencia-bcp' ? 'BCP' : 'INTERBANK',
                'fecha_transferencia' => now()->toDateString(),
                'estado' => 'CONFIRMADO',
                'monto_transferido' => rand(100, 5000),
            ],
            'paypal' => [
                'payment_id' => 'PAY-' . strtoupper(substr(uniqid(), -13)),
                'payer_id' => 'PAYER' . rand(100000, 999999),
                'status' => 'COMPLETED',
                'create_time' => now()->toISOString(),
                'update_time' => now()->toISOString(),
            ],
            default => [
                'transaction_id' => 'TXN' . rand(1000000, 9999999),
                'status' => 'SUCCESS',
                'processed_at' => now()->toISOString(),
            ],
        };
    }

    private function generarCodigoAutorizacion(string $tipoPago): ?string
    {
        return match ($tipoPago) {
            'yape' => 'YP' . rand(100000, 999999),
            'plin' => 'PL' . rand(100000, 999999),
            'visa', 'mastercard', 'tarjeta' => 'AUTH' . rand(100000, 999999),
            'transferencia-bcp', 'transferencia-interbank', 'transferencia' => 'TRF' . rand(10000000, 99999999),
            'paypal' => 'PP' . strtoupper(substr(uniqid(), -8)),
            default => 'TXN' . rand(100000, 999999),
        };
    }

    private function generarObservacionesPago(string $tipoPago, string $estado): ?string
    {
        if ($estado === 'pagado') {
            return match ($tipoPago) {
                'yape' => 'Pago confirmado mediante Yape - Procesamiento instantáneo',
                'plin' => 'Pago confirmado mediante Plin - Procesamiento instantáneo',
                'visa', 'mastercard', 'tarjeta' => 'Pago procesado con tarjeta - Autorización obtenida',
                'transferencia' => 'Transferencia bancaria confirmada - Comprobante validado',
                'efectivo' => 'Pago en efectivo confirmado al momento de la entrega',
                'paypal' => 'Pago procesado exitosamente mediante PayPal',
                default => 'Pago confirmado',
            };
        }

        return match ($estado) {
            'pendiente' => 'Pago en proceso de verificación',
            'fallido' => 'Pago rechazado - Verificar datos o saldo',
            'atrasado' => 'Pago vencido - Contactar con el cliente',
            'cancelado' => 'Pago cancelado por el usuario',
            'reembolsado' => 'Pago reembolsado exitosamente',
            default => null,
        };
    }
} 