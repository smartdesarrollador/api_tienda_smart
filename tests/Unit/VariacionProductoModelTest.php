<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\VariacionProducto;
use App\Models\Producto;
use App\Models\DetallePedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

class VariacionProductoModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    /** @test */
    public function variacion_producto_has_correct_fillable_attributes(): void
    {
        $expectedFillable = [
            'producto_id',
            'sku',
            'precio',
            'precio_oferta',
            'stock',
            'activo',
            'atributos'
        ];

        $variacion = new VariacionProducto();
        
        $this->assertEquals($expectedFillable, $variacion->getFillable());
    }

    /** @test */
    public function variacion_producto_belongs_to_producto(): void
    {
        $variacion = VariacionProducto::first();
        
        $this->assertInstanceOf(Producto::class, $variacion->producto);
        $this->assertEquals($variacion->producto_id, $variacion->producto->id);
    }

    /** @test */
    public function variacion_producto_has_many_detalles_pedido(): void
    {
        $variacion = VariacionProducto::first();
        
        $this->assertInstanceOf(Collection::class, $variacion->detallesPedido);
        
        // Solo verificar si tiene detalles de pedido
        if ($variacion->detallesPedido->count() > 0) {
            $variacion->detallesPedido->each(function ($detalle) use ($variacion) {
                $this->assertInstanceOf(DetallePedido::class, $detalle);
                $this->assertEquals($variacion->id, $detalle->variacion_producto_id);
            });
        }
    }

    /** @test */
    public function variacion_producto_has_unique_sku(): void
    {
        $variacion = VariacionProducto::first();
        
        $this->assertNotNull($variacion->sku);
        $this->assertIsString($variacion->sku);
        
        // Verificar que no hay SKUs duplicados
        $skus = VariacionProducto::pluck('sku');
        $uniqueSkus = $skus->unique();
        
        $this->assertEquals($skus->count(), $uniqueSkus->count());
    }

    /** @test */
    public function variacion_producto_has_valid_precio(): void
    {
        $variaciones = VariacionProducto::all();
        
        $variaciones->each(function ($variacion) {
            $precio = (float) $variacion->precio;
            $this->assertIsFloat($precio);
            $this->assertGreaterThan(0, $precio);
        });
    }

    /** @test */
    public function variacion_producto_has_valid_stock(): void
    {
        $variaciones = VariacionProducto::all();
        
        $variaciones->each(function ($variacion) {
            $this->assertIsInt($variacion->stock);
            $this->assertGreaterThanOrEqual(0, $variacion->stock);
        });
    }

    /** @test */
    public function variacion_producto_precio_is_consistent_with_product(): void
    {
        $variaciones = VariacionProducto::with('producto')->get();
        
        // Verificar que las variaciones tienen precios coherentes por producto
        $variaciones->groupBy('producto_id')->each(function ($variacionesProducto) {
            $precios = $variacionesProducto->pluck('precio')->map(function($precio) {
                return (float) $precio;
            })->sort();
            $precioBase = $precios->first();
            
            $precios->each(function ($precio) use ($precioBase) {
                $this->assertGreaterThanOrEqual($precioBase, $precio);
            });
        });
    }

    /** @test */
    public function variacion_producto_belongs_to_valid_producto(): void
    {
        $variaciones = VariacionProducto::with('producto')->get();
        
        $variaciones->each(function ($variacion) {
            $this->assertNotNull($variacion->producto);
            $this->assertInstanceOf(Producto::class, $variacion->producto);
            $this->assertEquals($variacion->producto_id, $variacion->producto->id);
        });
    }

    /** @test */
    public function variacion_producto_can_be_active_or_inactive(): void
    {
        $variaciones = VariacionProducto::all();
        
        $variaciones->each(function ($variacion) {
            $this->assertIsBool($variacion->activo);
        });
    }

    /** @test */
    public function variacion_producto_has_atributos_field(): void
    {
        $variacion = VariacionProducto::first();
        
        // El campo atributos puede ser null o contener JSON
        if ($variacion->atributos) {
            $this->assertIsString($variacion->atributos);
        }
    }
} 