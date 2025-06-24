<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Producto;
use App\Models\VariacionProducto;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PedidoSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    /** @test */
    public function pedido_creation_with_complete_flow(): void
    {
        $cliente = User::where('rol', 'cliente')->first();
        $variacion = VariacionProducto::with('producto')->first();
        
        // Crear pedido
        $pedido = Pedido::create([
            'numero' => 'PED-TEST-001',
            'usuario_id' => $cliente->id,
            'estado' => 'pendiente',
            'tipo_pago' => 'contado',
            'subtotal' => $variacion->precio * 2,
            'igv' => ($variacion->precio * 2) * 0.18,
            'total' => ($variacion->precio * 2) * 1.18,
            'fecha_pedido' => now()
        ]);

        // Crear detalle del pedido
        DetallePedido::create([
            'pedido_id' => $pedido->id,
            'variacion_producto_id' => $variacion->id,
            'cantidad' => 2,
            'precio_unitario' => $variacion->precio
        ]);

        // Verificaciones
        $this->assertDatabaseHas('pedidos', [
            'numero' => 'PED-TEST-001',
            'usuario_id' => $cliente->id
        ]);

        $this->assertDatabaseHas('detalles_pedidos', [
            'pedido_id' => $pedido->id,
            'variacion_producto_id' => $variacion->id,
            'cantidad' => 2
        ]);

        // Verificar relaciones
        $pedidoConRelaciones = Pedido::with(['usuario', 'detalles.variacionProducto.producto'])
            ->find($pedido->id);

        $this->assertEquals($cliente->id, $pedidoConRelaciones->usuario->id);
        $this->assertEquals(1, $pedidoConRelaciones->detalles->count());
        $this->assertEquals($variacion->producto->nombre, 
            $pedidoConRelaciones->detalles->first()->variacionProducto->producto->nombre);
    }

    /** @test */
    public function pedido_total_calculation_is_correct(): void
    {
        $pedidos = Pedido::with('detalles')->get();

        $pedidos->each(function ($pedido) {
            $subtotalCalculado = $pedido->detalles->sum(function ($detalle) {
                return $detalle->cantidad * $detalle->precio_unitario;
            });

            $igvCalculado = $subtotalCalculado * 0.18;
            $totalCalculado = $subtotalCalculado + $igvCalculado;

            $this->assertEquals($subtotalCalculado, $pedido->subtotal);
            $this->assertEquals($igvCalculado, $pedido->igv);
            $this->assertEquals($totalCalculado, $pedido->total);
        });
    }

    /** @test */
    public function pedido_can_have_multiple_productos(): void
    {
        $cliente = User::where('rol', 'cliente')->first();
        $variaciones = VariacionProducto::limit(3)->get();
        
        $subtotal = 0;
        foreach ($variaciones as $variacion) {
            $subtotal += $variacion->precio * 1; // cantidad 1
        }
        
        $igv = $subtotal * 0.18;
        $total = $subtotal + $igv;

        $pedido = Pedido::create([
            'numero' => 'PED-MULTI-001',
            'usuario_id' => $cliente->id,
            'estado' => 'pendiente',
            'tipo_pago' => 'contado',
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
            'fecha_pedido' => now()
        ]);

        foreach ($variaciones as $variacion) {
            DetallePedido::create([
                'pedido_id' => $pedido->id,
                'variacion_producto_id' => $variacion->id,
                'cantidad' => 1,
                'precio_unitario' => $variacion->precio
            ]);
        }

        $pedidoConDetalles = Pedido::with('detalles')->find($pedido->id);
        
        $this->assertEquals(3, $pedidoConDetalles->detalles->count());
        
        // Verificar que cada producto es diferente
        $productosIds = $pedidoConDetalles->detalles->pluck('variacion_producto_id');
        $this->assertEquals($productosIds->count(), $productosIds->unique()->count());
    }

    /** @test */
    public function pedido_with_different_payment_types(): void
    {
        $tiposPago = ['contado', 'tarjeta', 'transferencia', 'yape', 'plin'];
        
        foreach ($tiposPago as $tipoPago) {
            $pedidos = Pedido::where('tipo_pago', $tipoPago)->get();
            
            $this->assertGreaterThan(0, $pedidos->count(), 
                "No hay pedidos con tipo de pago: {$tipoPago}");
            
            $pedidos->each(function ($pedido) use ($tipoPago) {
                $this->assertEquals($tipoPago, $pedido->tipo_pago);
            });
        }
    }

    /** @test */
    public function pedido_states_are_valid(): void
    {
        $estadosValidos = ['pendiente', 'confirmado', 'en_proceso', 'enviado', 'entregado', 'cancelado'];
        
        $pedidos = Pedido::all();
        
        $pedidos->each(function ($pedido) use ($estadosValidos) {
            $this->assertContains($pedido->estado, $estadosValidos);
        });
    }

    /** @test */
    public function pedido_belongs_to_cliente_role_user(): void
    {
        $pedidos = Pedido::with('usuario')->get();
        
        $pedidos->each(function ($pedido) {
            $this->assertEquals('cliente', $pedido->usuario->rol);
        });
    }

    /** @test */
    public function pedido_can_calculate_products_total_quantity(): void
    {
        $pedido = Pedido::with('detalles')->first();
        
        $cantidadTotal = $pedido->detalles->sum('cantidad');
        
        $this->assertIsInt($cantidadTotal);
        $this->assertGreaterThan(0, $cantidadTotal);
    }

    /** @test */
    public function pedido_can_access_productos_through_detalles(): void
    {
        $pedido = Pedido::with('detalles.variacionProducto.producto')->first();
        
        $productos = $pedido->detalles->map(function ($detalle) {
            return $detalle->variacionProducto->producto;
        });
        
        $productos->each(function ($producto) {
            $this->assertInstanceOf(Producto::class, $producto);
            $this->assertNotNull($producto->nombre);
        });
    }

    /** @test */
    public function pedido_numero_is_unique(): void
    {
        $numeros = Pedido::pluck('numero');
        $uniqueNumeros = $numeros->unique();
        
        $this->assertEquals($numeros->count(), $uniqueNumeros->count());
    }

    /** @test */
    public function pedido_fecha_is_not_future(): void
    {
        $pedidos = Pedido::all();
        
        $pedidos->each(function ($pedido) {
            $this->assertLessThanOrEqual(now(), $pedido->fecha_pedido);
        });
    }
} 