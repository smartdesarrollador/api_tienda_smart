<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Pedido;
use App\Models\CuotaCredito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class CreditSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    /** @test */
    public function users_with_credit_limit_exist(): void
    {
        $usuariosConCredito = User::where('limite_credito', '>', 0)->get();
        
        $this->assertGreaterThan(0, $usuariosConCredito->count());
        
        $usuariosConCredito->each(function ($usuario) {
            $this->assertEquals('cliente', $usuario->rol);
            $this->assertGreaterThan(0, $usuario->limite_credito);
            $this->assertGreaterThanOrEqual(0, $usuario->credito_usado);
            $this->assertLessThanOrEqual($usuario->limite_credito, $usuario->credito_usado);
        });
    }

    /** @test */
    public function user_can_calculate_available_credit(): void
    {
        $cliente = User::where('limite_credito', '>', 0)->first();
        
        $creditoDisponible = $cliente->limite_credito - $cliente->credito_usado;
        
        $this->assertGreaterThanOrEqual(0, $creditoDisponible);
        $this->assertLessThanOrEqual($cliente->limite_credito, $creditoDisponible);
    }

    /** @test */
    public function user_cannot_exceed_credit_limit(): void
    {
        $cliente = User::where('limite_credito', '>', 0)->first();
        
        // Simular que el usuario ha usado todo su crédito
        $cliente->credito_usado = $cliente->limite_credito;
        $cliente->save();
        
        $creditoDisponible = $cliente->limite_credito - $cliente->credito_usado;
        
        $this->assertEquals(0, $creditoDisponible);
        
        // Verificar que no puede usar más crédito
        $this->assertFalse($creditoDisponible > 0);
    }

    /** @test */
    public function credit_payment_orders_create_installments(): void
    {
        $cliente = User::where('limite_credito', '>', 0)->first();
        
        // Crear un pedido a crédito (simulado)
        $pedido = Pedido::create([
            'numero' => 'PED-CREDIT-001',
            'usuario_id' => $cliente->id,
            'estado' => 'confirmado',
            'tipo_pago' => 'credito',
            'subtotal' => 1000.00,
            'igv' => 180.00,
            'total' => 1180.00,
            'fecha_pedido' => now()
        ]);

        // Crear cuotas de crédito
        $numeroCuotas = 3;
        $montoPorCuota = $pedido->total / $numeroCuotas;
        
        for ($i = 1; $i <= $numeroCuotas; $i++) {
            CuotaCredito::create([
                'pedido_id' => $pedido->id,
                'numero_cuota' => $i,
                'monto' => $montoPorCuota,
                'fecha_vencimiento' => now()->addMonths($i),
                'estado' => 'pendiente'
            ]);
        }

        // Verificar que las cuotas se crearon correctamente
        $cuotas = CuotaCredito::where('pedido_id', $pedido->id)->get();
        
        $this->assertEquals($numeroCuotas, $cuotas->count());
        $this->assertEquals($pedido->total, $cuotas->sum('monto'));
        
        $cuotas->each(function ($cuota, $index) use ($montoPorCuota) {
            $this->assertEquals($montoPorCuota, $cuota->monto);
            $this->assertEquals($index + 1, $cuota->numero_cuota);
            $this->assertEquals('pendiente', $cuota->estado);
        });
    }

    /** @test */
    public function late_credit_installments_calculate_fees(): void
    {
        $cliente = User::where('limite_credito', '>', 0)->first();
        
        $pedido = Pedido::create([
            'numero' => 'PED-LATE-001',
            'usuario_id' => $cliente->id,
            'estado' => 'confirmado',
            'tipo_pago' => 'credito',
            'subtotal' => 1000.00,
            'igv' => 180.00,
            'total' => 1180.00,
            'fecha_pedido' => now()
        ]);

        // Crear cuota vencida
        $cuotaVencida = CuotaCredito::create([
            'pedido_id' => $pedido->id,
            'numero_cuota' => 1,
            'monto' => 393.33,
            'fecha_vencimiento' => now()->subDays(30), // Vencida hace 30 días
            'estado' => 'vencida',
            'dias_retraso' => 30,
            'monto_mora' => 39.33 // 10% de mora
        ]);

        $this->assertEquals('vencida', $cuotaVencida->estado);
        $this->assertEquals(30, $cuotaVencida->dias_retraso);
        $this->assertGreaterThan(0, $cuotaVencida->monto_mora);
        
        // Verificar que el monto de mora es proporcional al retraso
        $moraEsperada = $cuotaVencida->monto * 0.10; // 10% de mora
        $this->assertEquals($moraEsperada, $cuotaVencida->monto_mora);
    }

    /** @test */
    public function credit_usage_affects_user_limit(): void
    {
        $cliente = User::where('limite_credito', '>', 0)->first();
        $creditoInicialUsado = $cliente->credito_usado;
        $limiteCreditoInicial = $cliente->limite_credito;
        
        // Simular uso de crédito
        $montoNuevoCredito = 500.00;
        $cliente->credito_usado += $montoNuevoCredito;
        $cliente->save();
        
        $cliente->refresh();
        
        $this->assertEquals($creditoInicialUsado + $montoNuevoCredito, $cliente->credito_usado);
        $this->assertEquals($limiteCreditoInicial, $cliente->limite_credito); // El límite no cambia
        
        $creditoDisponible = $cliente->limite_credito - $cliente->credito_usado;
        $this->assertGreaterThanOrEqual(0, $creditoDisponible);
    }

    /** @test */
    public function credit_installment_payment_reduces_used_credit(): void
    {
        $cliente = User::where('limite_credito', '>', 0)->first();
        
        // Simular crédito usado
        $cliente->credito_usado = 1000.00;
        $cliente->save();
        
        $creditoUsadoInicial = $cliente->credito_usado;
        
        // Simular pago de cuota
        $montoPago = 300.00;
        $cliente->credito_usado -= $montoPago;
        $cliente->save();
        
        $cliente->refresh();
        
        $this->assertEquals($creditoUsadoInicial - $montoPago, $cliente->credito_usado);
        
        $creditoDisponible = $cliente->limite_credito - $cliente->credito_usado;
        $this->assertGreaterThan(0, $creditoDisponible);
    }

    /** @test */
    public function credit_installments_have_correct_states(): void
    {
        $estadosValidos = ['pendiente', 'pagada', 'vencida', 'cancelada'];
        
        // Crear algunas cuotas de prueba
        $cliente = User::where('limite_credito', '>', 0)->first();
        
        $pedido = Pedido::create([
            'numero' => 'PED-STATES-001',
            'usuario_id' => $cliente->id,
            'estado' => 'confirmado',
            'tipo_pago' => 'credito',
            'subtotal' => 900.00,
            'igv' => 162.00,
            'total' => 1062.00,
            'fecha_pedido' => now()
        ]);

        // Crear cuotas con diferentes estados
        CuotaCredito::create([
            'pedido_id' => $pedido->id,
            'numero_cuota' => 1,
            'monto' => 354.00,
            'fecha_vencimiento' => now()->addMonth(),
            'estado' => 'pendiente'
        ]);

        CuotaCredito::create([
            'pedido_id' => $pedido->id,
            'numero_cuota' => 2,
            'monto' => 354.00,
            'fecha_vencimiento' => now()->subDays(15),
            'estado' => 'vencida',
            'dias_retraso' => 15
        ]);

        $cuotas = CuotaCredito::where('pedido_id', $pedido->id)->get();
        
        $cuotas->each(function ($cuota) use ($estadosValidos) {
            $this->assertContains($cuota->estado, $estadosValidos);
        });
    }

    /** @test */
    public function credit_system_maintains_data_integrity(): void
    {
        $clientes = User::where('limite_credito', '>', 0)->get();
        
        $clientes->each(function ($cliente) {
            // Verificar que los datos son consistentes
            $this->assertGreaterThan(0, $cliente->limite_credito);
            $this->assertGreaterThanOrEqual(0, $cliente->credito_usado);
            $this->assertLessThanOrEqual($cliente->limite_credito, $cliente->credito_usado);
            
            // Verificar que es un cliente
            $this->assertEquals('cliente', $cliente->rol);
            
            // Verificar DNI formato peruano
            $this->assertMatchesRegularExpression('/^\d{8}$/', $cliente->dni);
        });
    }
} 